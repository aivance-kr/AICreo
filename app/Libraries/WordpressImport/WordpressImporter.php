<?php

declare(strict_types=1);

namespace App\Libraries\WordpressImport;

use App\Libraries\WordpressImport\Dto\ImportedCategory;
use App\Libraries\WordpressImport\Dto\ImportedMenuItem;
use App\Models\BoardCategoryModel;
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
 * 게시판 구조는 두 방식 중 하나로 뽑는다.
 * - importMenuAsBoards(): 워드프레스 메뉴(외모 > 메뉴) 기준 — 메뉴 최상위 항목 1개가
 *   게시판 1개, 그 하위 항목(카테고리를 가리키는 것만)이 그 게시판의 board_categories다.
 *   boards.wp_term_id에는 실제 카테고리 term_id가 아니라 메뉴 항목 자신의 wp:post_id를
 *   담는다 — 커스텀 링크 등 카테고리가 아닌 항목도 게시판으로 만들어야 하기 때문.
 * - importCategoryHierarchyAsBoards(): 메뉴가 아예 없는 사이트용 대안 — 최상위
 *   카테고리(부모 없음)가 게시판, 그 아래 하위 카테고리(깊이 무관, 평탄화)가
 *   board_categories다.
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
        private readonly BoardCategoryModel $categoryModel = new BoardCategoryModel(),
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
     * 워드프레스 메뉴 하나를 게시판 구조로 이관 — 최상위 항목 = 게시판, 하위 항목
     * (카테고리를 가리키는 것만) = 그 게시판의 board_categories.
     *
     * @return array<string, array{boardId: int, categoryId: ?int}> wp 카테고리 nicename => 게시판/카테고리 매핑
     */
    public function importMenuAsBoards(WxrParser $parser, string $menuNicename): array
    {
        $items = $parser->navMenuItems($menuNicename);

        if ($items === []) {
            $this->warnings[] = "메뉴 \"{$menuNicename}\"에서 항목을 찾지 못했습니다.";

            return [];
        }

        $nicenameByTermId = [];

        foreach ($parser->categories() as $category) {
            $nicenameByTermId[$category->wpTermId] = $category->nicename;
        }

        $childrenByParent = [];

        foreach ($items as $item) {
            $childrenByParent[$item->parentWpItemId][] = $item;
        }

        $routing = [];

        foreach ($childrenByParent[0] ?? [] as $topItem) {
            $boardId = $this->upsertBoardFromMenuItem($topItem, $nicenameByTermId);

            if ($topItem->isCategoryTarget() && isset($nicenameByTermId[$topItem->objectId])) {
                $routing[$nicenameByTermId[$topItem->objectId]] = ['boardId' => $boardId, 'categoryId' => null];
            }

            foreach ($childrenByParent[$topItem->wpItemId] ?? [] as $order => $childItem) {
                if (! $childItem->isCategoryTarget() || ! isset($nicenameByTermId[$childItem->objectId])) {
                    $this->warnings[] = "메뉴 항목 \"{$childItem->title}\" — 카테고리 대상이 아니라 건너뜀";

                    continue;
                }

                $nicename   = $nicenameByTermId[$childItem->objectId];
                $categoryId = $this->upsertCategoryFromMenuItem($boardId, $childItem->objectId, $nicename, $childItem->title, $order);

                $routing[$nicename] = ['boardId' => $boardId, 'categoryId' => $categoryId];
            }
        }

        return $routing;
    }

    /**
     * @param array<int, string> $nicenameByTermId wp 카테고리 term_id => nicename
     */
    private function upsertBoardFromMenuItem(ImportedMenuItem $item, array $nicenameByTermId): int
    {
        $existing = $this->boardModel->where('wp_term_id', $item->wpItemId)->first();
        if ($existing) {
            return (int) $existing['id'];
        }

        $slug = $item->isCategoryTarget() && isset($nicenameByTermId[$item->objectId])
            ? $nicenameByTermId[$item->objectId]
            : $this->slugify($item->title, 'menu-' . $item->wpItemId);

        return (int) $this->boardModel->insert([
            'wp_term_id' => $item->wpItemId,
            'slug'       => $slug,
            'name'       => $item->title,
            'is_active'  => 1,
        ]);
    }

    private function upsertCategoryFromMenuItem(int $boardId, int $wpTermId, string $nicename, string $name, int $sortOrder): int
    {
        $existing = $this->categoryModel
            ->where('board_id', $boardId)
            ->where('wp_term_id', $wpTermId)
            ->first();

        if ($existing) {
            return (int) $existing['id'];
        }

        return (int) $this->categoryModel->insert([
            'board_id'   => $boardId,
            'wp_term_id' => $wpTermId,
            'slug'       => $nicename,
            'name'       => $name,
            'sort_order' => $sortOrder,
            'is_active'  => 1,
        ]);
    }

    private function slugify(string $text, string $fallback): string
    {
        $slug = strtolower(trim($text));
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '', '-');

        return $slug !== '' ? $slug : $fallback;
    }

    /**
     * 메뉴가 없는 사이트용 대안 — 최상위 카테고리(부모 없음) 1개를 게시판 1개로,
     * 그 아래 하위 카테고리 전부(깊이 무관, 평탄화)를 그 게시판의 board_categories로
     * 매핑한다. board_categories는 단일 계층이라 3단 이상 중첩돼도 같은 게시판
     * 아래 형제 카테고리로 들어간다.
     *
     * @return array<string, array{boardId: int, categoryId: ?int}> wp 카테고리 nicename => 게시판/카테고리 매핑
     */
    public function importCategoryHierarchyAsBoards(WxrParser $parser): array
    {
        $categories = $parser->categories();

        if ($categories === []) {
            $this->warnings[] = '카테고리가 없어 게시판을 만들지 못했습니다.';

            return [];
        }

        $byNicename = [];

        foreach ($categories as $category) {
            $byNicename[$category->nicename] = $category;
        }

        $childrenByParent = [];

        foreach ($categories as $category) {
            if ($category->isRoot()) {
                continue;
            }

            // 부모 nicename이 실제 존재하지 않는 끊어진 참조는 루트로 취급
            $parentNicename                      = isset($byNicename[$category->parentNicename]) ? $category->parentNicename : '';
            $childrenByParent[$parentNicename][] = $category;
        }

        $routing = [];

        foreach ($categories as $root) {
            if (! $root->isRoot()) {
                continue;
            }

            $boardId                  = $this->upsertBoardFromCategory($root);
            $routing[$root->nicename] = ['boardId' => $boardId, 'categoryId' => null];

            $order = 0;

            foreach ($this->flattenDescendants($root->nicename, $childrenByParent) as $descendant) {
                $categoryId                     = $this->upsertCategoryFromMenuItem($boardId, $descendant->wpTermId, $descendant->nicename, $descendant->name, $order);
                $routing[$descendant->nicename] = ['boardId' => $boardId, 'categoryId' => $categoryId];
                $order++;
            }
        }

        return $routing;
    }

    /**
     * @param array<string, list<ImportedCategory>> $childrenByParent
     *
     * @return list<ImportedCategory>
     */
    private function flattenDescendants(string $nicename, array $childrenByParent): array
    {
        $result = [];

        foreach ($childrenByParent[$nicename] ?? [] as $child) {
            $result[] = $child;

            foreach ($this->flattenDescendants($child->nicename, $childrenByParent) as $grandchild) {
                $result[] = $grandchild;
            }
        }

        return $result;
    }

    private function upsertBoardFromCategory(ImportedCategory $category): int
    {
        $existing = $this->boardModel->where('wp_term_id', $category->wpTermId)->first();
        if ($existing) {
            return (int) $existing['id'];
        }

        return (int) $this->boardModel->insert([
            'wp_term_id' => $category->wpTermId,
            'slug'       => $category->nicename,
            'name'       => $category->name,
            'is_active'  => 1,
        ]);
    }

    /**
     * @param array<string, array{boardId: int, categoryId: ?int}> $routing wp 카테고리 nicename => 게시판/카테고리 매핑
     *
     * @return array{pages: int, posts: int, skipped: int}
     */
    public function importPagesAndPosts(WxrParser $parser, array $routing): array
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

            // 워드프레스는 글 1개에 카테고리 여러 개(다대다) 허용, 이쪽은 단일 분류라
            // 첫 번째로 매핑되는 카테고리(=게시판) 하나만 대표로 선택한다.
            $target = null;

            foreach ($post->categoryNicenames as $nicename) {
                if (isset($routing[$nicename])) {
                    $target = $routing[$nicename];

                    break;
                }
            }

            if ($target === null) {
                $this->warnings[] = "글 wp_post_id={$post->wpPostId} — 매핑된 게시판 없음, 건너뜀";
                $counts['skipped']++;

                continue;
            }

            $data = [
                'wp_post_id'  => $post->wpPostId,
                'board_id'    => $target['boardId'],
                'category_id' => $target['categoryId'],
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
