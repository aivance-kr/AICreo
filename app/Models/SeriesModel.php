<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
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
        return $this->select('series.*, (SELECT COUNT(*) FROM post_series INNER JOIN posts ON posts.id = post_series.post_id WHERE post_series.series_id = series.id AND posts.deleted_at IS NULL) AS post_count')
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
        return $this->publicPostsBuilder()
            ->select('posts.id, posts.title, boards.slug AS board_slug')
            ->where('post_series.series_id', $seriesId)
            ->orderBy('post_series.series_order', 'ASC')
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
            ->select('posts.id, posts.title, post_series.series_order, posts.is_active, posts.is_secret')
            ->join('post_series', 'post_series.post_id = posts.id', 'inner')
            ->where('post_series.series_id', $seriesId)
            ->where('posts.deleted_at', null)
            ->orderBy('post_series.series_order', 'ASC')
            ->orderBy('posts.id', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * 시리즈에 넣을 글 검색 — 글 번호(숫자) 또는 제목 일부. 삭제 글 제외. is_member 는 이 시리즈 소속 여부.
     *
     * @return list<array<string, mixed>>
     */
    public function searchPosts(int $seriesId, string $keyword, int $limit = 20): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $builder = $this->db->table('posts')
            ->select('posts.id, posts.title, boards.name AS board_title')
            ->select('EXISTS (SELECT 1 FROM post_series WHERE post_series.post_id = posts.id AND post_series.series_id = ' . $seriesId . ') AS is_member', false)
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
     * 글들을 시리즈 맨 뒤에 입력 순서대로 붙인다. 다른 시리즈에 속해 있어도 그대로 두고 중복 소속시킨다.
     * 이미 이 시리즈에 속한 글은 건드리지 않고, 없는(삭제 포함) 글 번호는 건너뛴다.
     *
     * @param list<int> $postIds
     *
     * @return array{added: list<int>, already: list<int>, missing: list<int>}
     */
    public function attachPosts(int $seriesId, array $postIds): array
    {
        $result = ['added' => [], 'already' => [], 'missing' => []];
        if ($postIds === []) {
            return $result;
        }

        $existing = array_map('intval', array_column($this->db->table('posts')->select('id')
            ->whereIn('id', $postIds)->where('deleted_at', null)->get()->getResultArray(), 'id'));
        $members = array_map('intval', array_column($this->db->table('post_series')->select('post_id')
            ->where('series_id', $seriesId)->whereIn('post_id', $postIds)->get()->getResultArray(), 'post_id'));

        $this->db->transStart();
        $order = $this->nextOrder($seriesId);

        foreach ($postIds as $postId) {
            if (! in_array($postId, $existing, true)) {
                $result['missing'][] = $postId;
            } elseif (in_array($postId, $members, true)) {
                $result['already'][] = $postId;
            } else {
                $this->db->table('post_series')->insert(['series_id' => $seriesId, 'post_id' => $postId, 'series_order' => min($order++, 65535)]);
                $result['added'][] = $postId;
            }
        }
        $this->db->transComplete();

        self::clearCache($seriesId);

        return $result;
    }

    /**
     * 글을 시리즈에서 뺀다 (글은 그대로 남는다).
     */
    public function detachPost(int $seriesId, int $postId): void
    {
        $this->db->table('post_series')->where('series_id', $seriesId)->where('post_id', $postId)->delete();
        self::clearCache($seriesId);
    }

    /**
     * 주어진 글 번호 순서대로 이 시리즈 소속 글의 순서를 1부터 다시 매긴다 (소속이 아닌 번호는 무시).
     *
     * @param list<int> $postIds
     */
    public function reorderPosts(int $seriesId, array $postIds): void
    {
        $this->db->transStart();
        $order = 1;

        foreach (array_unique($postIds) as $postId) {
            $this->db->table('post_series')->where('series_id', $seriesId)->where('post_id', $postId)
                ->update(['series_order' => min($order, 65535)]);
            if ($this->db->affectedRows() > 0) {
                $order++;
            }
        }
        $this->db->transComplete();

        self::clearCache($seriesId);
    }

    /**
     * 글이 속한 시리즈 번호 → 순서 (글쓰기 화면의 현재 선택값).
     *
     * @return array<int, int|null>
     */
    public function seriesOfPost(int $postId): array
    {
        $map = [];

        foreach ($this->db->table('post_series')->select('series_id, series_order')->where('post_id', $postId)->orderBy('series_id', 'ASC')->get()->getResultArray() as $row) {
            $map[(int) $row['series_id']] = $row['series_order'] === null ? null : (int) $row['series_order'];
        }

        return $map;
    }

    /**
     * 글의 시리즈 소속을 주어진 목록으로 맞춘다 (없는 시리즈는 무시, 순서를 비우면 맨 뒤).
     *
     * @param array<int, int|null> $wanted 시리즈 번호 → 순서(없으면 null)
     */
    public function syncPostSeries(int $postId, array $wanted): void
    {
        $current = $this->seriesOfPost($postId);
        $valid   = $wanted === [] ? [] : array_map('intval', array_column($this->db->table('series')->select('id')->whereIn('id', array_keys($wanted))->get()->getResultArray(), 'id'));

        $this->db->transStart();

        foreach (array_diff(array_keys($current), $valid) as $seriesId) {
            $this->db->table('post_series')->where('series_id', $seriesId)->where('post_id', $postId)->delete();
        }

        foreach ($valid as $seriesId) {
            $order = $wanted[$seriesId] ?? null;
            if ($order === null && array_key_exists($seriesId, $current)) {
                continue; // 순서를 비워 두면 기존 소속은 그대로
            }
            $order ??= $this->nextOrder($seriesId);
            $row = ['series_id' => $seriesId, 'post_id' => $postId, 'series_order' => min($order, 65535)];
            if (array_key_exists($seriesId, $current)) {
                $this->db->table('post_series')->where('series_id', $seriesId)->where('post_id', $postId)->update(['series_order' => $row['series_order']]);
            } else {
                $this->db->table('post_series')->insert($row);
            }
        }
        $this->db->transComplete();

        foreach (array_unique(array_merge(array_keys($current), $valid)) as $seriesId) {
            self::clearCache($seriesId);
        }
    }

    /**
     * 글 상세 화면용 — 글이 속한 모든 시리즈의 내비게이션 (공개 목록에 없는 시리즈는 제외).
     *
     * @return list<array<string, mixed>>
     */
    public function navigationsForPost(int $postId): array
    {
        $navs = [];

        foreach (array_keys($this->seriesOfPost($postId)) as $seriesId) {
            $nav = $this->navigation($seriesId, $postId);
            if ($nav !== null) {
                $navs[] = $nav;
            }
        }

        return $navs;
    }

    /**
     * 공개 시리즈 목록 — 공개 글이 1편 이상인 시리즈만, 최근 글이 있는 순서로. post_count 는 공개 글 수.
     *
     * @return list<array<string, mixed>>
     */
    public function listPublic(): array
    {
        return $this->select('series.*')
            ->select('COUNT(posts.id) AS post_count, MAX(posts.id) AS last_post_id', false)
            ->join('post_series', 'post_series.series_id = series.id', 'inner')
            ->join('posts', 'posts.id = post_series.post_id AND posts.deleted_at IS NULL AND posts.is_active = 1 AND posts.is_secret = 0', 'inner')
            ->join('boards', 'boards.id = posts.board_id AND boards.is_active = 1 AND boards.read_permission = \'guest\'', 'inner')
            ->groupBy('series.id')
            ->orderBy('last_post_id', 'DESC')
            ->findAll();
    }

    /**
     * AI 시리즈 추천용 후보 — 최근 시리즈 순으로 번호·제목·소개와 최근 공개 글 제목(최대 $samples 개).
     *
     * @return list<array{id: int, title: string, description: string|null, samples: list<string>}>
     */
    public function suggestionCandidates(int $limit = 50, int $samples = 5): array
    {
        $series = $this->select('id, title, description')->orderBy('id', 'DESC')->findAll($limit);
        if ($series === []) {
            return [];
        }

        $rows = $this->publicPostsBuilder()
            ->select('post_series.series_id, posts.title')
            ->whereIn('post_series.series_id', array_column($series, 'id'))
            ->orderBy('posts.id', 'DESC')
            ->limit($limit * $samples * 2)
            ->get()->getResultArray();

        $titles = [];

        foreach ($rows as $row) {
            $key = (int) $row['series_id'];
            if (count($titles[$key] ?? []) < $samples) {
                $titles[$key][] = (string) $row['title'];
            }
        }

        return array_map(static fn (array $item): array => [
            'id'          => (int) $item['id'],
            'title'       => (string) $item['title'],
            'description' => $item['description'],
            'samples'     => $titles[(int) $item['id']] ?? [],
        ], $series);
    }

    /**
     * 공개 글 조회용 공통 쿼리 — 비밀글·숨김글·삭제글·비공개 게시판 글 제외.
     */
    private function publicPostsBuilder(): BaseBuilder
    {
        return $this->db->table('post_series')
            ->join('posts', 'posts.id = post_series.post_id', 'inner')
            ->join('boards', 'boards.id = posts.board_id', 'inner')
            ->where('posts.deleted_at', null)
            ->where('posts.is_active', 1)
            ->where('posts.is_secret', 0)
            ->where('boards.is_active', 1)
            ->where('boards.read_permission', 'guest');
    }

    /**
     * 순서를 비워 둔 글에 줄 번호 — 시리즈의 마지막 번호 + 1.
     */
    public function nextOrder(int $seriesId): int
    {
        $row = $this->db->table('post_series')->selectMax('series_order')
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
     * 시리즈를 지울 때 글은 남기고 소속(피벗 행)만 지운다 (외래 키 대신 코드로 처리).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function detachPosts(array $data): array
    {
        foreach ((array) ($data['id'] ?? []) as $id) {
            $this->db->table('post_series')->where('series_id', (int) $id)->delete();
            self::clearCache((int) $id);
        }

        return $data;
    }
}
