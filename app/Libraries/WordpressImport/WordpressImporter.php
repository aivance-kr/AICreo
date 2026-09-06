<?php

declare(strict_types=1);

namespace App\Libraries\WordpressImport;

use App\Models\BoardModel;
use App\Models\MediaModel;
use App\Models\PageModel;
use App\Models\PostCommentModel;
use App\Models\PostModel;
use App\Models\RedirectModel;
use App\Models\UserModel;
use CodeIgniter\Model;

/**
 * WxrParser 결과를 AiCreo 스키마에 upsert.
 *
 * 전부 wp_*_id 컬럼 매칭 기반 upsert라 재실행해도 안전(idempotent) — 이미 만든
 * board_id 뒤에도 여러 페이지 이관 가능, 중간 실패 후 재실행해도 중복 안 생김.
 * 네이티브(관리자 작성) 콘텐츠는 이 컬럼이 전부 NULL이라 절대 안 섞인다.
 *
 * 허용 이미지 확장자 화이트리스트 — FileUploader 와 별개 정의(업로드 요청이 아니라
 * 이미 검증된 원본 사이트 파일을 그대로 받아오는 경로라 재사용 강제할 이유 없음).
 */
final class WordpressImporter
{
    private const ALLOWED_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    private const EXT_MIME_MAP = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf',
    ];

    /**
     * @var list<string>
     */
    private array $warnings = [];

    public function __construct(
        private readonly BoardModel $boardModel = new BoardModel(),
        private readonly PageModel $pageModel = new PageModel(),
        private readonly PostModel $postModel = new PostModel(),
        private readonly PostCommentModel $commentModel = new PostCommentModel(),
        private readonly MediaModel $mediaModel = new MediaModel(),
        private readonly UserModel $userModel = new UserModel(),
        private readonly RedirectModel $redirectModel = new RedirectModel(),
    ) {
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return array<string, int> wp_term_id => board_id
     */
    public function importCategories(WxrParser $parser): array
    {
        $map = [];

        foreach ($parser->categories() as $category) {
            $existing = $this->boardModel->where('wp_term_id', $category->wpTermId)->first();

            if ($existing) {
                $map[$category->nicename] = (int) $existing['id'];

                continue;
            }

            $boardId = $this->boardModel->insert([
                'wp_term_id'       => $category->wpTermId,
                'slug'             => $category->nicename,
                'name'             => $category->name,
                'read_permission'  => 'guest',
                'write_permission' => 'admin',
                'is_active'        => 1,
            ], true);

            $map[$category->nicename] = (int) $boardId;
        }

        return $map;
    }

    /**
     * @param array<string, int> $boardIdByNicename
     *
     * @return array{pages: int, posts: int, skipped: int}
     */
    public function importPagesAndPosts(WxrParser $parser, array $boardIdByNicename): array
    {
        $counts = ['pages' => 0, 'posts' => 0, 'skipped' => 0];

        foreach ($parser->pages() as $page) {
            if ($page->status !== 'publish') {
                $counts['skipped']++;

                continue;
            }

            $data = [
                'wp_post_id' => $page->wpPostId,
                'slug'       => $page->slug,
                'title'      => $page->title,
                'content'    => $page->content,
                'meta_desc'  => $page->metaDescription,
                'status'     => 'published',
                'created_at' => $page->postDate,
                'updated_at' => $page->postDate,
            ];

            $this->upsertByWpId($this->pageModel, 'wp_post_id', $page->wpPostId, $data);
            $counts['pages']++;
        }

        foreach ($parser->posts() as $post) {
            if ($post->status !== 'publish' && $post->status !== 'private') {
                $counts['skipped']++;

                continue;
            }

            $boardId = null;

            foreach ($post->categoryNicenames as $nicename) {
                if (isset($boardIdByNicename[$nicename])) {
                    $boardId = $boardIdByNicename[$nicename];

                    break;
                }
            }

            if ($boardId === null) {
                $this->warnings[] = "글 wp_post_id={$post->wpPostId} — 매핑된 게시판 없음, 건너뜀";
                $counts['skipped']++;

                continue;
            }

            $data = [
                'wp_post_id'  => $post->wpPostId,
                'board_id'    => $boardId,
                'user_id'     => $this->resolveAuthorId($post->authorLogin),
                'title'       => $post->title,
                'content'     => $post->content,
                'author_name' => $post->authorLogin,
                'is_secret'   => $post->isPrivate() ? 1 : 0,
                'views'       => $post->views,
                'created_at'  => $post->postDate,
                'updated_at'  => $post->postDate,
            ];

            $this->upsertByWpId($this->postModel, 'wp_post_id', $post->wpPostId, $data);
            $counts['posts']++;
        }

        return $counts;
    }

    /**
     * 첨부파일을 워드프레스 uploads 디렉터리에서 직접 복사해 public/uploads/imported/ 에 저장.
     * 같은 서버에서 이관하는 전제 — 네트워크 다운로드 없이 파일시스템 복사만 함.
     *
     * @param string $wpUploadsDir 워드프레스 wp-content/uploads 절대경로
     *
     * @return array{copied: int, failed: int, pathMap: array<string, string>} pathMap: 옛 상대경로(예: 2001/08/x.jpg) => 새 상대경로
     */
    public function importAttachments(WxrParser $parser, string $wpUploadsDir): array
    {
        $copied       = 0;
        $failed       = 0;
        $pathMap      = [];
        $wpUploadsDir = rtrim($wpUploadsDir, '/');

        foreach ($parser->attachments() as $attachment) {
            $ext = strtolower((string) pathinfo($attachment->attachmentUrl, PATHINFO_EXTENSION));
            if (! in_array($ext, self::ALLOWED_EXTS, true)) {
                $this->warnings[] = "첨부파일 wp_post_id={$attachment->wpPostId} — 허용 안 된 확장자({$ext}), 건너뜀";
                $failed++;

                continue;
            }

            $existing = $this->mediaModel->where('wp_attachment_id', $attachment->wpPostId)->first();
            if ($existing) {
                if ($attachment->attachedFile !== '') {
                    $pathMap[$attachment->attachedFile] = $existing['file_path'];
                }
                $copied++;

                continue;
            }

            $relativeFile = $attachment->attachedFile !== ''
                ? $attachment->attachedFile
                : $this->relativeFromAttachmentUrl($attachment->attachmentUrl);

            if ($relativeFile === null) {
                $this->warnings[] = "첨부파일 wp_post_id={$attachment->wpPostId} — 상대경로 파악 불가, 건너뜀";
                $failed++;

                continue;
            }

            $srcPath = $wpUploadsDir . '/' . $relativeFile;
            if (! is_file($srcPath)) {
                $this->warnings[] = "원본 파일 없음: {$srcPath}";
                $failed++;

                continue;
            }

            $destPath = FCPATH . 'uploads/imported/' . $relativeFile;
            $destDir  = dirname($destPath);
            if (! is_dir($destDir) && ! mkdir($destDir, 0755, true) && ! is_dir($destDir)) {
                $this->warnings[] = "디렉터리 생성 실패: {$destDir}";
                $failed++;

                continue;
            }

            if (! copy($srcPath, $destPath)) {
                $this->warnings[] = "파일 복사 실패: {$srcPath}";
                $failed++;

                continue;
            }

            $relativePath = 'uploads/imported/' . $relativeFile;

            $this->mediaModel->insert([
                'wp_attachment_id' => $attachment->wpPostId,
                'original_name'    => basename($relativeFile),
                'stored_name'      => basename($relativeFile),
                'file_path'        => $relativePath,
                'file_size'        => (int) filesize($destPath),
                'mime_type'        => self::EXT_MIME_MAP[$ext],
                'created_at'       => $attachment->postDate,
            ]);

            if ($attachment->attachedFile !== '') {
                $pathMap[$attachment->attachedFile] = $relativePath;
            }
            $copied++;
        }

        return ['copied' => $copied, 'failed' => $failed, 'pathMap' => $pathMap];
    }

    /**
     * wp:attachment_url 에 _wp_attached_file 메타가 없을 때, URL 자체에서
     * "wp-content/uploads/" 뒤의 상대경로를 뽑아낸다.
     */
    private function relativeFromAttachmentUrl(string $url): ?string
    {
        $marker = 'wp-content/uploads/';
        $pos    = strpos($url, $marker);
        if ($pos === false) {
            return null;
        }

        return substr($url, $pos + strlen($marker));
    }

    /**
     * 본문(content) 안의 옛 wp-content/uploads 경로를 새 경로로 치환.
     *
     * @param array<string, string> $pathMap 옛 상대경로 => 새 상대경로
     */
    public function rewriteContentImages(array $pathMap): int
    {
        if ($pathMap === []) {
            return 0;
        }

        $updated = 0;

        foreach ([$this->postModel, $this->pageModel] as $model) {
            $rows = $model->where('wp_post_id IS NOT NULL')->findAll();

            foreach ($rows as $row) {
                $original = $row['content'];
                $content  = $original;

                foreach ($pathMap as $oldRelative => $newRelative) {
                    $content = str_replace(
                        ['wp-content/uploads/' . $oldRelative],
                        [$newRelative],
                        $content,
                    );
                }

                if ($content !== $original) {
                    $model->update($row['id'], ['content' => $content]);
                    $updated++;
                }
            }
        }

        return $updated;
    }

    public function importComments(WxrParser $parser): int
    {
        $imported = 0;

        foreach ($parser->comments() as $comment) {
            $post = $this->postModel->where('wp_post_id', $comment->wpPostId)->first();
            if (! $post) {
                continue;
            }

            $this->upsertByWpId($this->commentModel, 'wp_comment_id', $comment->wpCommentId, [
                'wp_comment_id' => $comment->wpCommentId,
                'post_id'       => (int) $post['id'],
                'author_name'   => $comment->authorName,
                'content'       => $comment->content,
                'ip_address'    => $comment->ipAddress,
                'created_at'    => $comment->date,
            ]);
            $imported++;
        }

        return $imported;
    }

    /**
     * 이관된 글·페이지마다 구주소(쿼리스트링 퍼머링크 포함) → 새 주소 리다이렉트 생성.
     */
    public function buildRedirects(WxrParser $parser, string $siteLink): int
    {
        $created = 0;

        foreach ($parser->pages() as $page) {
            if ($page->status !== 'publish') {
                continue;
            }
            $oldPath = $this->extractPath($page->link, $siteLink);
            if ($oldPath === null) {
                continue;
            }
            $this->redirectModel->upsert($oldPath, '/' . $page->slug);
            $created++;
        }

        foreach ($parser->posts() as $post) {
            if ($post->status !== 'publish' && $post->status !== 'private') {
                continue;
            }
            $row = $this->postModel->where('wp_post_id', $post->wpPostId)->first();
            if (! $row) {
                continue;
            }
            $oldPath = $this->extractPath($post->link, $siteLink);
            if ($oldPath === null) {
                continue;
            }
            $board = $this->boardModel->find($row['board_id']);
            if (! $board) {
                continue;
            }
            $this->redirectModel->upsert($oldPath, '/board/' . $board['slug'] . '/' . $row['id']);
            $created++;
        }

        return $created;
    }

    private function extractPath(string $link, string $siteLink): ?string
    {
        if (! str_starts_with($link, $siteLink)) {
            return null;
        }

        $path = substr($link, strlen($siteLink));

        return $path === '' ? '/' : $path;
    }

    private function resolveAuthorId(string $login): ?int
    {
        if ($login === '') {
            return null;
        }

        $existing = $this->userModel->where('username', $login)->first();
        if ($existing) {
            return (int) $existing['id'];
        }

        return (int) $this->userModel->insert([
            'username'  => $login,
            'email'     => $login . '@wp-import.invalid',
            'password'  => password_hash(bin2hex(random_bytes(16)), PASSWORD_ARGON2ID),
            'nickname'  => $login,
            'role'      => 'member',
            'is_active' => 0,
        ], true);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function upsertByWpId(Model $model, string $wpIdField, int $wpId, array $data): void
    {
        $existing = $model->where($wpIdField, $wpId)->first();

        if ($existing) {
            $model->update($existing['id'], $data);

            return;
        }

        $model->insert($data);
    }
}
