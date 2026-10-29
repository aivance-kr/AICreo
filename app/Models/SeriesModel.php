<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class SeriesModel extends Model
{
    public const STATUSES = ['ongoing' => '연재중', 'completed' => '완결'];

    private const CACHE_TTL = 3600;

    protected $table         = 'series';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['slug', 'title', 'description', 'status'];
    protected $afterUpdate   = ['clearNavCache'];
    protected $beforeDelete  = ['detachPosts'];

    public static function cacheKey(int $seriesId): string
    {
        return 'series_nav_' . $seriesId;
    }

    public static function clearCache(int $seriesId): void
    {
        cache()->delete(self::cacheKey($seriesId));
    }

    /**
     * 소개 페이지용 시리즈 조회 (slug).
     *
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->where('slug', $slug)->first();
    }

    /**
     * 시리즈별 글 수 포함 목록 (관리자 화면용).
     *
     * @return list<array<string, mixed>>
     */
    public function listWithCounts(): array
    {
        return $this->select('series.*, (SELECT COUNT(*) FROM posts WHERE posts.series_id = series.id AND posts.deleted_at IS NULL) AS post_count')
            ->orderBy('series.id', 'DESC')
            ->findAll();
    }

    /**
     * 시리즈에 속한 공개 글 목록 — 연재 순서(series_order), 같으면 글 번호 순.
     * 비밀글·숨김글·삭제글·비공개 게시판 글은 제외한다.
     *
     * @return list<array{id: int|string, title: string, board_slug: string}>
     */
    public function publicPosts(int $seriesId): array
    {
        return $this->db->table('posts')
            ->select('posts.id, posts.title, boards.slug AS board_slug')
            ->join('boards', 'boards.id = posts.board_id', 'inner')
            ->where('posts.series_id', $seriesId)
            ->where('posts.deleted_at', null)
            ->where('posts.is_active', 1)
            ->where('posts.is_secret', 0)
            ->where('boards.is_active', 1)
            ->where('boards.read_permission', 'guest')
            ->orderBy('posts.series_order', 'ASC')
            ->orderBy('posts.id', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * 글 상세 화면용 시리즈 내비게이션. 현재 글이 시리즈의 공개 목록에 없으면 null.
     * 캐시에는 시리즈 단위 목록만 넣고, 현재 위치·이전·다음은 매번 계산한다.
     *
     * @return array{series: array<string, mixed>, posts: list<array<string, mixed>>, current: int, total: int, prev: array<string, mixed>|null, next: array<string, mixed>|null}|null
     */
    public function navigation(int $seriesId, int $postId): ?array
    {
        $nav = cache()->remember(self::cacheKey($seriesId), self::CACHE_TTL, function () use ($seriesId): ?array {
            $series = $this->find($seriesId);

            return $series === null ? null : ['series' => $series, 'posts' => $this->publicPosts($seriesId)];
        });

        if ($nav === null) {
            return null;
        }

        $index = array_search($postId, array_map('intval', array_column($nav['posts'], 'id')), true);
        if ($index === false) {
            return null;
        }

        return [
            'series'  => $nav['series'],
            'posts'   => $nav['posts'],
            'current' => $index + 1,
            'total'   => count($nav['posts']),
            'prev'    => $nav['posts'][$index - 1] ?? null,
            'next'    => $nav['posts'][$index + 1] ?? null,
        ];
    }

    /**
     * 순서를 비워 둔 글에 줄 번호 — 시리즈의 마지막 번호 + 1.
     */
    public function nextOrder(int $seriesId): int
    {
        $row = $this->db->table('posts')->selectMax('series_order')
            ->where('series_id', $seriesId)->get()->getRow();

        return (int) ($row->series_order ?? 0) + 1;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function clearNavCache(array $data): array
    {
        foreach ((array) ($data['id'] ?? []) as $id) {
            self::clearCache((int) $id);
        }

        return $data;
    }

    /**
     * 시리즈를 지울 때 글은 남기고 소속만 해제한다 (외래 키 대신 코드로 처리).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function detachPosts(array $data): array
    {
        foreach ((array) ($data['id'] ?? []) as $id) {
            $this->db->table('posts')->where('series_id', (int) $id)->update(['series_id' => null, 'series_order' => null]);
            self::clearCache((int) $id);
        }

        return $data;
    }
}
