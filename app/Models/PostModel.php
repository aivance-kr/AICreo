<?php

namespace App\Models;

use CodeIgniter\Model;
use DateTimeImmutable;

class PostModel extends Model
{
    public const MAX_TAGS                   = 10;
    public const MAX_TAG_LENGTH             = 30;
    private const RELATED_CACHE_VERSION_KEY = 'related_posts_version';
    private const RELATED_CACHE_TTL         = 3600;
    private const TAG_COUNTS_CACHE_KEY      = 'public_tag_counts';
    private const TAG_COUNTS_CACHE_TTL      = 3600;

    /**
     * 목록용 공감수·댓글수(활성 댓글만) 서브쿼리
     */
    private const COUNT_SUBQUERIES = '(SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) AS like_count, '
        . '(SELECT COUNT(*) FROM post_comments WHERE post_comments.post_id = posts.id AND post_comments.is_active = 1) AS comment_count';

    /**
     * "많이 본 글" 기간 키 → 표시명. all 은 누적 조회수.
     */
    public const POPULAR_PERIODS = ['all' => '전체', '7d' => '7일', '30d' => '30일'];

    private const ARCHIVE_CACHE_KEY = 'archive_counts';
    private const ARCHIVE_CACHE_TTL = 3600;
    private const POPULAR_DAYS      = ['7d' => 7, '30d' => 30];
    private const POPULAR_CACHE_TTL = 600;

    protected $table          = 'posts';
    protected $primaryKey     = 'id';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $allowedFields  = [
        'board_id', 'category_id', 'user_id', 'title', 'content', 'signature', 'tags',
        'author_name', 'author_password',
        'is_notice', 'is_secret', 'is_active', 'publish_at', 'newsletter_enqueued_at', 'ip_address',
        'views', 'wp_post_id', 'created_at', 'updated_at',
    ];
    protected $afterInsert = ['clearSeoCache', 'clearRelatedCache', 'clearTagCountsCache', 'clearArchiveCache', 'enqueueNewsletter'];
    protected $afterUpdate = ['clearSeoCache', 'clearSeriesCache', 'clearRelatedCache', 'clearTagCountsCache', 'clearArchiveCache', 'enqueueNewsletter'];
    protected $afterDelete = ['clearSeoCache', 'clearSeriesCache', 'clearRelatedCache', 'clearTagCountsCache', 'clearArchiveCache'];

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
            ->where('posts.is_active', 1)
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
            ->where('posts.is_active', 1)
            ->where('boards.is_active', 1)
            ->where('boards.read_permission', 'guest')
            ->orderBy('posts.id', 'DESC')
            ->findAll($limit);
    }

    /**
     * 공개 글 중 많이 본 글 — getLatestPublic 과 같은 공개 조건.
     * all 은 posts.views 누적, 7d/30d 는 page_visits 의 해당 글 주소 방문 수 기준이다.
     * 모르는 기간 값은 all 로 취급한다.
     *
     * @return list<array<string, mixed>> 기간 집계일 때는 visit_count 가 함께 담긴다
     */
    public function getPopular(string $period, int $limit): array
    {
        $builder = $this->select('posts.*, boards.name AS board_name, boards.slug AS board_slug')
            ->join('boards', 'boards.id = posts.board_id', 'inner')
            ->where('posts.is_secret', 0)
            ->where('posts.is_active', 1)
            ->where('boards.is_active', 1)
            ->where('boards.read_permission', 'guest');

        $days = self::POPULAR_DAYS[$period] ?? null;

        if ($days === null) {
            return $builder->orderBy('posts.views', 'DESC')->orderBy('posts.id', 'DESC')->findAll($limit);
        }

        $since = date('Y-m-d 00:00:00', strtotime("-{$days} days"));

        return $builder->select('COUNT(v.id) AS visit_count', false)
            ->join(
                'page_visits v',
                "v.uri = CONCAT('/board/', boards.slug, '/', posts.id) AND v.created_at >= " . $this->db->escape($since),
                'inner',
            )
            ->groupBy('posts.id, boards.name, boards.slug')
            ->orderBy('visit_count', 'DESC')
            ->orderBy('posts.id', 'DESC')
            ->findAll($limit);
    }

    /**
     * getPopular 결과를 캐시한다(10분). 글 수정 시 무효화하지 않고 TTL 만료에 맡긴다.
     *
     * @return list<array<string, mixed>>
     */
    public function getPopularCached(string $period, int $limit): array
    {
        $period = isset(self::POPULAR_PERIODS[$period]) ? $period : 'all';

        return cache()->remember(
            "popular_posts_{$period}_{$limit}",
            self::POPULAR_CACHE_TTL,
            fn (): array => $this->getPopular($period, $limit),
        );
    }

    /**
     * 공개 글의 연·월별 글 수(최신 월 먼저) — getLatestPublic 과 같은 공개 조건.
     * 캐시하고 글 저장·삭제 때 clearArchiveCache 로 무효화한다.
     *
     * @return list<array{year: int, month: int, count: int}>
     */
    public function getArchiveCounts(): array
    {
        return cache()->remember(self::ARCHIVE_CACHE_KEY, self::ARCHIVE_CACHE_TTL, function (): array {
            $rows = $this->select('YEAR(posts.created_at) AS y, MONTH(posts.created_at) AS m, COUNT(*) AS cnt', false)
                ->join('boards', 'boards.id = posts.board_id', 'inner')
                ->where('posts.is_secret', 0)
                ->where('posts.is_active', 1)
                ->where('boards.is_active', 1)
                ->where('boards.read_permission', 'guest')
                ->groupBy('y, m')
                ->orderBy('y', 'DESC')
                ->orderBy('m', 'DESC')
                ->findAll();

            return array_map(
                static fn (array $r): array => ['year' => (int) $r['y'], 'month' => (int) $r['m'], 'count' => (int) $r['cnt']],
                $rows,
            );
        });
    }

    /**
     * 해당 연·월에 작성된 공개 글(최신순) — getLatestPublic 과 같은 공개 조건.
     *
     * @return array{posts: list<array<string, mixed>>, total: int}
     */
    public function getArchivePosts(int $year, int $month, int $page, int $perPage): array
    {
        $from = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $to   = date('Y-m-d H:i:s', (int) strtotime($from . ' +1 month'));

        $builder = $this->select('posts.*, boards.name AS board_name, boards.slug AS board_slug')
            ->join('boards', 'boards.id = posts.board_id', 'inner')
            ->where('posts.is_secret', 0)
            ->where('posts.is_active', 1)
            ->where('boards.is_active', 1)
            ->where('boards.read_permission', 'guest')
            ->where('posts.created_at >=', $from)
            ->where('posts.created_at <', $to);

        $total = (clone $builder)->countAllResults(false);
        $posts = $builder->orderBy('posts.id', 'DESC')->findAll($perPage, ($page - 1) * $perPage);

        return ['posts' => $posts, 'total' => $total];
    }

    /**
     * OG 이미지를 만들 수 있는 공개 글(제목만) — 비밀글·비활성·비공개 게시판 글은 null.
     *
     * @return array{title: string}|null
     */
    public function getPublicOgSource(int $postId): ?array
    {
        $row = $this->select('posts.title')
            ->join('boards', 'boards.id = posts.board_id', 'inner')
            ->where('posts.id', $postId)
            ->where('posts.is_secret', 0)
            ->where('posts.is_active', 1)
            ->where('boards.is_active', 1)
            ->where('boards.read_permission', 'guest')
            ->first();

        return $row === null ? null : ['title' => (string) $row['title']];
    }

    /**
     * 사이트 전체 공개 글 검색 (제목·본문) — getLatestPublic 과 같은 공개 조건을 적용한다.
     *
     * @return array{posts: list<array<string, mixed>>, total: int}
     */
    public function searchPublic(string $keyword, int $page, int $perPage): array
    {
        $builder = $this->select('posts.*, boards.name AS board_name, boards.slug AS board_slug, users.nickname AS user_nickname')
            ->join('boards', 'boards.id = posts.board_id', 'inner')
            ->join('users', 'users.id = posts.user_id', 'left')
            ->where('posts.is_secret', 0)
            ->where('posts.is_active', 1)
            ->where('boards.is_active', 1)
            ->where('boards.read_permission', 'guest')
            ->groupStart()
            ->like('posts.title', $keyword)
            ->orLike('posts.content', $keyword)
            ->groupEnd();

        $total = (clone $builder)->countAllResults(false);
        $posts = $builder->orderBy('posts.id', 'DESC')->findAll($perPage, ($page - 1) * $perPage);

        return ['posts' => $posts, 'total' => $total];
    }

    /**
     * 공개 글의 태그별 글 수 — getLatestPublic 과 같은 공개 조건. 많은 순, 같으면 이름순.
     * 대소문자만 다른 태그는 하나로 합친다(먼저 나온 표기를 쓴다). 결과는 캐시하고 글 변경 때 무효화한다.
     *
     * @return array<string, int> 태그 => 글 수
     */
    public function getPublicTagCounts(): array
    {
        return cache()->remember(self::TAG_COUNTS_CACHE_KEY, self::TAG_COUNTS_CACHE_TTL, function (): array {
            $rows = $this->select('posts.tags')
                ->join('boards', 'boards.id = posts.board_id', 'inner')
                ->where('posts.is_secret', 0)
                ->where('posts.is_active', 1)
                ->where('boards.is_active', 1)
                ->where('boards.read_permission', 'guest')
                ->where('posts.tags IS NOT NULL')
                ->orderBy('posts.id', 'DESC')
                ->findAll();

            $names  = [];
            $counts = [];

            foreach ($rows as $row) {
                foreach (self::splitTags($row['tags']) as $tag) {
                    $key = mb_strtolower($tag);
                    $names[$key] ??= $tag;
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
            }

            $result = [];

            foreach ($counts as $key => $count) {
                $result[$names[$key]] = $count;
            }
            uksort($result, static fn (string $a, string $b): int => $result[$b] <=> $result[$a] ?: strcmp($a, $b));

            return $result;
        });
    }

    /**
     * 사이드바 태그 클라우드용 상위 N개 태그.
     *
     * @return array<string, int> 태그 => 글 수
     */
    public function getTagCloud(int $limit): array
    {
        return array_slice($this->getPublicTagCounts(), 0, $limit, true);
    }

    /**
     * 사이트 전체 공개 글 중 해당 태그를 가진 글 — getLatestPublic 과 같은 공개 조건.
     *
     * @return array{posts: list<array<string, mixed>>, total: int}
     */
    public function searchPublicByTag(string $tag, int $page, int $perPage): array
    {
        $builder = $this->select('posts.*, boards.name AS board_name, boards.slug AS board_slug, users.nickname AS user_nickname')
            ->join('boards', 'boards.id = posts.board_id', 'inner')
            ->join('users', 'users.id = posts.user_id', 'left')
            ->where('posts.is_secret', 0)
            ->where('posts.is_active', 1)
            ->where('boards.is_active', 1)
            ->where('boards.read_permission', 'guest')
            ->where('FIND_IN_SET(' . $this->db->escape($tag) . ', posts.tags) >', 0, false);

        $total = (clone $builder)->countAllResults(false);
        $posts = $builder->orderBy('posts.id', 'DESC')->findAll($perPage, ($page - 1) * $perPage);

        return ['posts' => $posts, 'total' => $total];
    }

    /**
     * 상세 하단 "이어 읽을 글" — 같은 태그를 하나라도 공유하거나 같은 카테고리인 다른 공개 글.
     * 태그 겹침 수가 많은 글이 먼저, 같으면 최신 글이 먼저. 공개 조건은 getLatestPublic 과 같다.
     * 결과는 캐시하고 글 저장·삭제 때 clearRelatedCache 로 무효화한다.
     *
     * @return list<array{id: int, title: string, board_slug: string, thumb: string|null}>
     */
    public function getRelated(int $postId, int $limit = 5): array
    {
        $version = cache()->get(self::RELATED_CACHE_VERSION_KEY) ?? '0';

        return cache()->remember(
            "related_posts_{$version}_{$postId}_{$limit}",
            self::RELATED_CACHE_TTL,
            fn (): array => $this->queryRelated($postId, $limit),
        );
    }

    /**
     * @return list<array{id: int, title: string, board_slug: string, thumb: string|null}>
     */
    private function queryRelated(int $postId, int $limit): array
    {
        $current = $this->select('tags, category_id')->find($postId);
        if ($current === null) {
            return [];
        }

        $matches  = [];
        $overlaps = [];

        foreach (self::splitTags($current['tags']) as $tag) {
            $expr       = 'FIND_IN_SET(' . $this->db->escape($tag) . ', posts.tags) > 0';
            $matches[]  = $expr;
            $overlaps[] = "({$expr})";
        }
        if ($current['category_id'] !== null) {
            $matches[] = 'posts.category_id = ' . (int) $current['category_id'];
        }
        if ($matches === []) {
            return [];
        }

        $builder = $this->select('posts.id, posts.title, posts.content, boards.slug AS board_slug')
            ->join('boards', 'boards.id = posts.board_id', 'inner')
            ->where('posts.id !=', $postId)
            ->where('posts.is_secret', 0)
            ->where('posts.is_active', 1)
            ->where('boards.is_active', 1)
            ->where('boards.read_permission', 'guest')
            ->where('(' . implode(' OR ', $matches) . ')', null, false);
        // 태그 겹침 수가 많은 글 먼저 (태그가 없는 글은 카테고리 일치만으로 후보가 되므로 건너뛴다)
        if ($overlaps !== []) {
            $builder->orderBy(implode(' + ', $overlaps), 'DESC', false);
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = $builder->orderBy('posts.id', 'DESC')->findAll($limit);

        helper('image');

        return array_map(static function (array $row): array {
            $thumb = self::extractThumbnail($row['content']);

            return [
                'id'         => (int) $row['id'],
                'title'      => (string) $row['title'],
                'board_slug' => (string) $row['board_slug'],
                'thumb'      => $thumb === null ? null : content_thumb_url($thumb),
            ];
        }, $rows);
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
     * 입력 태그 문자열(쉼표 구분)을 정리한다 — 공백 정리·'#' 제거·중복 제거·개수/길이 제한.
     * 결과가 없으면 null.
     */
    public static function normalizeTags(?string $raw): ?string
    {
        $tags = [];

        foreach (explode(',', (string) $raw) as $part) {
            $tag = mb_substr(trim(ltrim(trim($part), '#')), 0, self::MAX_TAG_LENGTH);
            if ($tag !== '' && ! in_array(mb_strtolower($tag), array_map('mb_strtolower', $tags), true)) {
                $tags[] = $tag;
            }
        }

        return $tags === [] ? null : implode(',', array_slice($tags, 0, self::MAX_TAGS));
    }

    /**
     * 저장된 태그 문자열을 배열로 푼다.
     *
     * @return list<string>
     */
    public static function splitTags(?string $tags): array
    {
        return $tags === null || $tags === '' ? [] : explode(',', $tags);
    }

    /**
     * 글이 공개 상태가 되면 뉴스레터 발송 큐에 올린다(글당 한 번 — 표식은 enqueuePost 가 관리).
     * 예약·숨김·비밀글·비공개 게시판 글은 공개될 때까지 대상이 아니다. 실제 발송은 newsletter:send 가 한다.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function enqueueNewsletter(array $data): array
    {
        $ids = array_filter((array) ($data['id'] ?? []));
        if ($ids === []) {
            return $data;
        }

        $rows = $this->db->table('posts p')->select('p.id')
            ->join('boards b', 'b.id = p.board_id')
            ->whereIn('p.id', $ids)
            ->where('p.newsletter_enqueued_at IS NULL')
            ->where('p.is_active', 1)->where('p.is_secret', 0)->where('p.deleted_at IS NULL')
            ->where('b.is_active', 1)->where('b.read_permission', 'guest')
            ->get()->getResultArray();

        $subscribers = new SubscriberModel();

        foreach ($rows as $row) {
            $subscribers->enqueuePost((int) $row['id']);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function clearArchiveCache(array $data): array
    {
        cache()->delete(self::ARCHIVE_CACHE_KEY);

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function clearSeoCache(array $data): array
    {
        cache()->delete('seo_sitemap');
        cache()->delete('seo_feed');

        return $data;
    }

    /**
     * 글이 바뀌면 모든 글의 관련 글 캐시를 무효화한다 — 한 글의 태그·공개 여부가 다른 글들의 관련 글에 영향을 주므로
     * 글 단위가 아니라 버전 토큰을 바꿔 한꺼번에 끊는다.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function clearRelatedCache(array $data): array
    {
        cache()->save(self::RELATED_CACHE_VERSION_KEY, uniqid('', true), 0);

        return $data;
    }

    /**
     * 글이 바뀌면 공개 태그 집계 캐시를 지운다.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function clearTagCountsCache(array $data): array
    {
        cache()->delete(self::TAG_COUNTS_CACHE_KEY);

        return $data;
    }

    /**
     * 글이 바뀌면 속한 시리즈들의 내비게이션 캐시를 지운다.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function clearSeriesCache(array $data): array
    {
        $ids = array_filter((array) ($data['id'] ?? []));
        if ($ids !== []) {
            $rows = $this->db->table('post_series')->select('series_id')->distinct()->whereIn('post_id', $ids)->get()->getResultArray();

            foreach ($rows as $row) {
                SeriesModel::clearCache((int) $row['series_id']);
            }
        }

        return $data;
    }

    /**
     * @return array{notices: list<array<string, mixed>>, posts: list<array<string, mixed>>}
     */
    public function getList(int $boardId, int $page, int $perPage, ?int $categoryId = null): array
    {
        $offset = ($page - 1) * $perPage;

        $noticeBuilder = $this->select('posts.*, board_categories.name as category_name, ' . self::COUNT_SUBQUERIES)
            ->join('board_categories', 'board_categories.id = posts.category_id', 'left')
            ->where('posts.board_id', $boardId)
            ->where('posts.is_notice', 1)
            ->where('posts.is_active', 1);
        if ($categoryId !== null) {
            $noticeBuilder->where('posts.category_id', $categoryId);
        }
        $notices = $noticeBuilder->orderBy('posts.id', 'DESC')->findAll(5);

        $postBuilder = $this->select('posts.*, users.nickname as user_nickname, board_categories.name as category_name, ' . self::COUNT_SUBQUERIES)
            ->join('users', 'users.id = posts.user_id', 'left')
            ->join('board_categories', 'board_categories.id = posts.category_id', 'left')
            ->where('posts.board_id', $boardId)
            ->where('posts.is_notice', 0)
            ->where('posts.is_active', 1);
        if ($categoryId !== null) {
            $postBuilder->where('posts.category_id', $categoryId);
        }
        $posts = $postBuilder->orderBy('posts.id', 'DESC')->findAll($perPage, $offset);

        return ['notices' => $notices, 'posts' => $posts];
    }

    public function getTotalCount(int $boardId, ?int $categoryId = null): int
    {
        $builder = $this->where('board_id', $boardId)->where('is_notice', 0)->where('is_active', 1);
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
     * 같은 게시판의 이전 글(더 오래된 글) — 공지·비밀글·숨김글 제외. $categoryId 지정 시 그 카테고리 안에서만 찾는다.
     *
     * @return array<string, mixed>|null
     */
    public function getPrevious(int $boardId, int $postId, ?int $categoryId = null): ?array
    {
        $builder = $this->select('id, title')
            ->where('board_id', $boardId)
            ->where('is_notice', 0)
            ->where('is_secret', 0)
            ->where('is_active', 1)
            ->where('id <', $postId);

        if ($categoryId !== null) {
            $builder->where('category_id', $categoryId);
        }

        return $builder->orderBy('id', 'DESC')->first();
    }

    /**
     * 같은 게시판의 다음 글(더 최근 글) — 공지·비밀글·숨김글 제외. $categoryId 지정 시 그 카테고리 안에서만 찾는다.
     *
     * @return array<string, mixed>|null
     */
    public function getNext(int $boardId, int $postId, ?int $categoryId = null): ?array
    {
        $builder = $this->select('id, title')
            ->where('board_id', $boardId)
            ->where('is_notice', 0)
            ->where('is_secret', 0)
            ->where('is_active', 1)
            ->where('id >', $postId);

        if ($categoryId !== null) {
            $builder->where('category_id', $categoryId);
        }

        return $builder->orderBy('id', 'ASC')->first();
    }

    /**
     * 노출 상태를 반전한다(관리자 숨기기/노출 토글). update() 경유로
     * afterUpdate 훅(clearSeoCache)이 정상 실행되게 한다.
     */
    public function toggleActive(int $id): void
    {
        $post = $this->find($id);
        if ($post === null) {
            return;
        }

        // 예약 글은 토글하면 즉시 공개하고 예약을 푼다
        $this->update($id, ['is_active' => $post['is_active'] ? 0 : 1, 'publish_at' => null]);
    }

    /**
     * 예약 입력(datetime-local 값)을 DB 값으로 바꾼다. 형식이 틀리거나 이미 지난 시각이면 null(= 즉시 공개).
     */
    public static function normalizePublishAt(?string $input): ?string
    {
        if ($input === null || $input === '') {
            return null;
        }

        $time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $input);
        if ($time === false || $time->getTimestamp() <= time()) {
            return null;
        }

        return $time->format('Y-m-d H:i:00');
    }

    /**
     * 예약 시각이 지난 글을 공개 처리하고 예약을 비운다.
     * update() 경유로 사이트맵·RSS·관련 글·시리즈 캐시 훅이 실행된다.
     *
     * @return int 공개 처리한 글 수
     */
    public function publishDue(): int
    {
        $ids = array_column(
            $this->select('id')->where('publish_at IS NOT NULL')->where('publish_at <=', date('Y-m-d H:i:s'))->findAll(),
            'id',
        );

        foreach ($ids as $id) {
            $this->update($id, ['is_active' => 1, 'publish_at' => null]);
        }

        return count($ids);
    }

    /**
     * 게시물을 다른 게시판으로 일괄 이동한다. 카테고리는 게시판별이므로 비운다.
     * update() 경유로 afterUpdate 훅(clearSeoCache)이 실행된다.
     *
     * @param list<int> $ids
     */
    public function moveToBoard(array $ids, int $boardId): void
    {
        $this->update($ids, ['board_id' => $boardId, 'category_id' => null]);
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
        $builder = $this->select('posts.*, users.nickname as user_nickname, board_categories.name as category_name, ' . self::COUNT_SUBQUERIES)
            ->join('users', 'users.id = posts.user_id', 'left')
            ->join('board_categories', 'board_categories.id = posts.category_id', 'left')
            ->where('posts.board_id', $boardId)
            ->where('posts.is_active', 1);

        if ($categoryId !== null) {
            $builder->where('posts.category_id', $categoryId);
        }

        if ($type === 'title') {
            $builder->like('posts.title', $keyword);
        } elseif ($type === 'tag') {
            // 태그는 쉼표 구분 저장 — 정확히 일치하는 태그만 (태그 자체에는 쉼표가 없다)
            $builder->where('FIND_IN_SET(' . $this->db->escape($keyword) . ', posts.tags) >', 0, false);
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
