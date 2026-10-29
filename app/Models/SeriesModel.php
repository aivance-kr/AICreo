<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class SeriesModel extends Model
{
    public const STATUSES   = ['ongoing' => '연재중', 'completed' => '완결'];
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
     * "12, 15,20" 형태의 글 번호 문자열을 중복 없는 양의 정수 목록으로 바꾼다 (입력 순서 유지).
     *
     * @return list<int>
     */
    public static function parsePostIds(string $raw): array
    {
        $ids = [];

        foreach (preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            if (ctype_digit($token) && (int) $token > 0) {
                $ids[(int) $token] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * 관리자 화면용 소속 글 목록 (비공개·숨김 포함, 삭제 글 제외) — 연재 순서, 같으면 글 번호 순.
     *
     * @return list<array<string, mixed>>
     */
    public function memberPosts(int $seriesId): array
    {
        return $this->db->table('posts')
            ->select('posts.id, posts.title, posts.series_order, posts.is_active, posts.is_secret')
            ->where('posts.series_id', $seriesId)
            ->where('posts.deleted_at', null)
            ->orderBy('posts.series_order', 'ASC')
            ->orderBy('posts.id', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * 시리즈에 넣을 글 검색 — 글 번호(숫자) 또는 제목 일부. 삭제 글 제외.
     *
     * @return list<array<string, mixed>>
     */
    public function searchPosts(string $keyword, int $limit = 20): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $builder = $this->db->table('posts')
            ->select('posts.id, posts.title, posts.series_id, boards.name AS board_title')
            ->join('boards', 'boards.id = posts.board_id', 'left')
            ->where('posts.deleted_at', null)
            ->groupStart()
            ->like('posts.title', $keyword);

        if (ctype_digit($keyword)) {
            $builder->orWhere('posts.id', (int) $keyword);
        }

        return $builder->groupEnd()
            ->orderBy('posts.id', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();
    }

    /**
     * 글들을 시리즈 맨 뒤에 입력 순서대로 붙인다. 다른 시리즈에 속한 글은 옮겨 온다.
     * 이미 이 시리즈에 속한 글은 건드리지 않고, 없는(삭제 포함) 글 번호는 건너뛴다.
     *
     * @param list<int> $postIds
     *
     * @return array{added: list<int>, moved: list<int>, already: list<int>, missing: list<int>}
     */
    public function attachPosts(int $seriesId, array $postIds): array
    {
        $result = ['added' => [], 'moved' => [], 'already' => [], 'missing' => []];
        if ($postIds === []) {
            return $result;
        }

        $rows = $this->db->table('posts')->select('id, series_id')
            ->whereIn('id', $postIds)->where('deleted_at', null)
            ->get()->getResultArray();
        $current = array_column($rows, 'series_id', 'id');

        $this->db->transStart();
        $order            = $this->nextOrder($seriesId);
        $touchedSeriesIds = [$seriesId];

        foreach ($postIds as $postId) {
            if (! array_key_exists($postId, $current)) {
                $result['missing'][] = $postId;

                continue;
            }

            $previous = $current[$postId] === null ? null : (int) $current[$postId];
            if ($previous === $seriesId) {
                $result['already'][] = $postId;

                continue;
            }

            $this->db->table('posts')->where('id', $postId)
                ->update(['series_id' => $seriesId, 'series_order' => min($order++, 65535)]);

            if ($previous === null) {
                $result['added'][] = $postId;
            } else {
                $result['moved'][]  = $postId;
                $touchedSeriesIds[] = $previous;
            }
        }
        $this->db->transComplete();

        foreach (array_unique($touchedSeriesIds) as $id) {
            self::clearCache($id);
        }

        return $result;
    }

    /**
     * 글을 시리즈에서 뺀다 (글은 그대로 남는다).
     */
    public function detachPost(int $seriesId, int $postId): void
    {
        $this->db->table('posts')->where('id', $postId)->where('series_id', $seriesId)
            ->update(['series_id' => null, 'series_order' => null]);
        self::clearCache($seriesId);
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
