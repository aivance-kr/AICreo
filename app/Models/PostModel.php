<?php

namespace App\Models;

use CodeIgniter\Model;

class PostModel extends Model
{
    protected $table          = 'posts';
    protected $primaryKey     = 'id';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $allowedFields  = [
        'board_id', 'category_id', 'user_id', 'title', 'content',
        'author_name', 'author_password',
        'is_notice', 'is_secret', 'ip_address',
        'views', 'wp_post_id', 'created_at', 'updated_at',
    ];
    protected $afterInsert = ['clearSitemapCache'];
    protected $afterUpdate = ['clearSitemapCache'];
    protected $afterDelete = ['clearSitemapCache'];

    /**
     * sitemap.xml 용 공개 글 목록 — 비밀글·비활성 게시판 제외.
     * (소프트삭제 글은 모델이 자동 제외)
     *
     * @return list<array{id:int,updated_at:string|null,board_slug:string}>
     */
    public function getPublicForSitemap(): array
    {
        return $this->select('posts.id, posts.updated_at, boards.slug AS board_slug')
            ->join('boards', 'boards.id = posts.board_id', 'inner')
            ->where('posts.is_secret', 0)
            ->where('boards.is_active', 1)
            ->where('boards.read_permission', 'guest')
            ->orderBy('posts.id', 'DESC')
            ->findAll();
    }

    /**
     * 블로그 메인에 표시할 공개 최신 글을 반환한다.
     *
     * @return list<array<string, mixed>>
     */
    public function getLatestPublic(int $limit): array
    {
        return $this->select('posts.*, boards.name AS board_name, boards.slug AS board_slug, users.nickname AS user_nickname')
            ->join('boards', 'boards.id = posts.board_id', 'inner')
            ->join('users', 'users.id = posts.user_id', 'left')
            ->where('posts.is_secret', 0)
            ->where('boards.is_active', 1)
            ->where('boards.read_permission', 'guest')
            ->orderBy('posts.id', 'DESC')
            ->findAll($limit);
    }

    /**
     * 게시글 본문 HTML에서 첫 번째 이미지 src 를 추출한다 (블로그형/갤러리형 목록 썸네일용).
     */
    public static function extractThumbnail(?string $content): ?string
    {
        if ($content === null || $content === '') {
            return null;
        }

        // 워드프레스 이관 게시글 일부는 img src 속성에 따옴표가 없다 — 따옴표 유무 모두 대응.
        if (preg_match('/<img[^>]+src=(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/i', $content, $m) !== 1) {
            return null;
        }

        return $m[1] !== '' ? $m[1] : (($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? ''));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function clearSitemapCache(array $data): array
    {
        cache()->delete('seo_sitemap');

        return $data;
    }

    /**
     * @return array{notices: list<array<string, mixed>>, posts: list<array<string, mixed>>}
     */
    public function getList(int $boardId, int $page, int $perPage, ?int $categoryId = null): array
    {
        $offset = ($page - 1) * $perPage;

        $noticeBuilder = $this->select('posts.*, board_categories.name as category_name')
            ->join('board_categories', 'board_categories.id = posts.category_id', 'left')
            ->where('posts.board_id', $boardId)
            ->where('posts.is_notice', 1);
        if ($categoryId !== null) {
            $noticeBuilder->where('posts.category_id', $categoryId);
        }
        $notices = $noticeBuilder->orderBy('posts.id', 'DESC')->findAll(5);

        $postBuilder = $this->select('posts.*, users.nickname as user_nickname, board_categories.name as category_name')
            ->join('users', 'users.id = posts.user_id', 'left')
            ->join('board_categories', 'board_categories.id = posts.category_id', 'left')
            ->where('posts.board_id', $boardId)
            ->where('posts.is_notice', 0);
        if ($categoryId !== null) {
            $postBuilder->where('posts.category_id', $categoryId);
        }
        $posts = $postBuilder->orderBy('posts.id', 'DESC')->findAll($perPage, $offset);

        return ['notices' => $notices, 'posts' => $posts];
    }

    public function getTotalCount(int $boardId, ?int $categoryId = null): int
    {
        $builder = $this->where('board_id', $boardId)->where('is_notice', 0);
        if ($categoryId !== null) {
            $builder->where('category_id', $categoryId);
        }

        return $builder->countAllResults();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDetail(int $id): ?array
    {
        return $this->select('posts.*, users.nickname as user_nickname, users.email as user_email, board_categories.name as category_name')
            ->join('users', 'users.id = posts.user_id', 'left')
            ->join('board_categories', 'board_categories.id = posts.category_id', 'left')
            ->find($id);
    }

    public function incrementView(int $id): void
    {
        $this->db->query('UPDATE posts SET views = views + 1 WHERE id = ?', [$id]);
    }

    /**
     * 같은 게시판의 이전 글(더 오래된 글) — 공지·비밀글 제외.
     *
     * @return array<string, mixed>|null
     */
    public function getPrevious(int $boardId, int $postId): ?array
    {
        return $this->select('id, title')
            ->where('board_id', $boardId)
            ->where('is_notice', 0)
            ->where('is_secret', 0)
            ->where('id <', $postId)
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * 같은 게시판의 다음 글(더 최근 글) — 공지·비밀글 제외.
     *
     * @return array<string, mixed>|null
     */
    public function getNext(int $boardId, int $postId): ?array
    {
        return $this->select('id, title')
            ->where('board_id', $boardId)
            ->where('is_notice', 0)
            ->where('is_secret', 0)
            ->where('id >', $postId)
            ->orderBy('id', 'ASC')
            ->first();
    }

    /**
     * @return array{posts: list<array<string, mixed>>, total: int}
     */
    public function getAdminList(int $page, int $perPage, string $keyword = '', int $boardId = 0): array
    {
        $builder = $this->select('posts.*, boards.name as board_name, boards.slug as board_slug, users.nickname as user_nickname')
            ->join('boards', 'boards.id = posts.board_id', 'left')
            ->join('users', 'users.id = posts.user_id', 'left');

        if ($boardId > 0) {
            $builder->where('posts.board_id', $boardId);
        }
        if ($keyword !== '') {
            $builder->groupStart()
                ->like('posts.title', $keyword)
                ->orLike('posts.author_name', $keyword)
                ->orLike('users.nickname', $keyword)
                ->groupEnd();
        }

        $total = (clone $builder)->countAllResults(false);
        $posts = $builder->orderBy('posts.id', 'DESC')
            ->findAll($perPage, ($page - 1) * $perPage);

        return ['posts' => $posts, 'total' => $total];
    }

    /**
     * @return array{posts: list<array<string, mixed>>, total: int}
     */
    public function search(int $boardId, string $keyword, string $type, int $page, int $perPage, ?int $categoryId = null): array
    {
        $offset  = ($page - 1) * $perPage;
        $builder = $this->select('posts.*, users.nickname as user_nickname, board_categories.name as category_name')
            ->join('users', 'users.id = posts.user_id', 'left')
            ->join('board_categories', 'board_categories.id = posts.category_id', 'left')
            ->where('posts.board_id', $boardId);

        if ($categoryId !== null) {
            $builder->where('posts.category_id', $categoryId);
        }

        if ($type === 'title') {
            $builder->like('posts.title', $keyword);
        } elseif ($type === 'content') {
            $builder->like('posts.content', $keyword);
        } else {
            $builder->groupStart()
                ->like('posts.title', $keyword)
                ->orLike('posts.content', $keyword)
                ->groupEnd();
        }

        $total = (clone $builder)->countAllResults(false);
        $posts = $builder->orderBy('posts.id', 'DESC')->findAll($perPage, $offset);

        return ['posts' => $posts, 'total' => $total];
    }
}
