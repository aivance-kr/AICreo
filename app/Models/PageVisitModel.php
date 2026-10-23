<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;
use Throwable;

class PageVisitModel extends Model
{
    /**
     * 검색엔진 호스트 끝부분 → [표시명, 검색어 쿼리 파라미터]
     */
    private const SEARCH_ENGINES = [
        'naver.com'    => ['네이버', 'query'],
        'google.com'   => ['구글', 'q'],
        'google.co.kr' => ['구글', 'q'],
        'daum.net'     => ['다음', 'q'],
        'bing.com'     => ['빙', 'q'],
    ];

    protected $table         = 'page_visits';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $allowedFields = ['uri', 'ip_address', 'user_agent', 'referer', 'is_not_found'];

    public function logVisit(string $uri, string $ipAddress, ?string $userAgent, ?string $referer): int
    {
        return (int) $this->insert([
            'uri'        => $uri,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'referer'    => $referer,
        ], true);
    }

    public function markNotFound(int $id): void
    {
        $this->builder()->where('id', $id)->update(['is_not_found' => 1]);
    }

    /**
     * $since 이후 $windowSeconds 초 안에 존재하지 않는 주소로 $threshold 회 이상 접속한 IP.
     *
     * @return list<string>
     */
    public function notFoundBurstIps(Time $since, int $threshold, int $windowSeconds): array
    {
        $rows = $this->builder()
            ->select('ip_address, created_at')
            ->where('is_not_found', 1)
            ->where('created_at >=', $since->toDateTimeString())
            ->orderBy('ip_address')
            ->orderBy('created_at')
            ->get()
            ->getResultArray();

        $timesByIp = [];

        foreach ($rows as $row) {
            $timesByIp[(string) $row['ip_address']][] = strtotime((string) $row['created_at']);
        }

        $ips = [];

        foreach ($timesByIp as $ip => $times) {
            for ($i = 0, $last = count($times) - $threshold; $i <= $last; $i++) {
                if ($windowSeconds >= $times[$i + $threshold - 1] - $times[$i]) {
                    $ips[] = (string) $ip;
                    break;
                }
            }
        }

        return $ips;
    }

    public function countSince(Time $since): int
    {
        return $this->where('created_at >=', $since->toDateTimeString())->countAllResults();
    }

    public function countUniqueIpsSince(Time $since): int
    {
        $row = $this->select('COUNT(DISTINCT ip_address) as cnt')
            ->where('created_at >=', $since->toDateTimeString())
            ->first();

        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * 최근 $days일간 일별 방문 수 (데이터 없는 날짜는 0으로 채운다).
     *
     * @return list<array{date: string, count: int}>
     */
    public function dailyCounts(int $days): array
    {
        $since = Time::now()->subDays($days - 1)->setTime(0, 0, 0);

        $rows = $this->select('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at >=', $since->toDateTimeString())
            ->groupBy('DATE(created_at)')
            ->findAll();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['date']] = (int) $row['count'];
        }

        $result = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date     = Time::now()->subDays($i)->format('Y-m-d');
            $result[] = ['date' => $date, 'count' => $counts[$date] ?? 0];
        }

        return $result;
    }

    /**
     * 최근 $days일간 인기 페이지 상위 $limit건.
     *
     * @return list<array{uri: string, count: int}>
     */
    public function topPages(int $days, int $limit = 10): array
    {
        $since = Time::now()->subDays($days)->setTime(0, 0, 0);

        $rows = $this->select('uri, COUNT(*) as count')
            ->where('created_at >=', $since->toDateTimeString())
            ->groupBy('uri')
            ->orderBy('count', 'DESC')
            ->limit($limit)
            ->findAll();

        return array_map(
            static fn (array $row): array => ['uri' => (string) $row['uri'], 'count' => (int) $row['count']],
            $rows,
        );
    }

    /**
     * 기간 검색 값을 [시작, 끝(미포함)] 범위로 변환한다. 형식이 틀리면 null.
     *
     * - day: Y-m-d / week: ISO 주차(2026-W41) / month: Y-m / year: Y
     *
     * @return array{start: Time, end: Time, label: string}|null
     */
    public static function periodRange(string $period, string $value): ?array
    {
        try {
            $range = match ($period) {
                'day' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
                    ? [Time::parse($value), Time::parse($value)->addDays(1), $value] : null,
                'week' => preg_match('/^(\d{4})-W(\d{2})$/', $value, $m) === 1
                    ? [
                        Time::now()->setISODate((int) $m[1], (int) $m[2])->setTime(0, 0, 0),
                        Time::now()->setISODate((int) $m[1], (int) $m[2])->setTime(0, 0, 0)->addDays(7),
                        $m[1] . '년 ' . (int) $m[2] . '주차',
                    ] : null,
                'month' => preg_match('/^\d{4}-\d{2}$/', $value) === 1
                    ? [Time::parse($value . '-01'), Time::parse($value . '-01')->addMonths(1), $value] : null,
                'year' => preg_match('/^\d{4}$/', $value) === 1
                    ? [Time::parse($value . '-01-01'), Time::parse($value . '-01-01')->addYears(1), $value . '년'] : null,
                default => null,
            };
        } catch (Throwable) {
            return null;
        }

        if ($range === null) {
            return null;
        }

        return ['start' => $range[0], 'end' => $range[1], 'label' => $range[2]];
    }

    public function countBetween(Time $start, Time $end): int
    {
        return $this->betweenQuery($start, $end)->countAllResults();
    }

    public function countUniqueIpsBetween(Time $start, Time $end): int
    {
        $row = $this->select('COUNT(DISTINCT ip_address) as cnt')
            ->where('created_at >=', $start->toDateTimeString())
            ->where('created_at <', $end->toDateTimeString())
            ->first();

        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * 기간 내 일별(또는 월별) 방문 수. 데이터 없는 구간은 0으로 채운다.
     *
     * @return list<array{key: string, count: int}> key 는 일별 Y-m-d, 월별 Y-m
     */
    public function bucketCounts(Time $start, Time $end, bool $monthly): array
    {
        $format = $monthly ? '%Y-%m' : '%Y-%m-%d';

        $rows = $this->select("DATE_FORMAT(created_at, '{$format}') as bucket, COUNT(*) as count", false)
            ->where('created_at >=', $start->toDateTimeString())
            ->where('created_at <', $end->toDateTimeString())
            ->groupBy('bucket')
            ->findAll();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['bucket']] = (int) $row['count'];
        }

        $result = [];

        for ($cursor = $start; $cursor->isBefore($end); $cursor = $monthly ? $cursor->addMonths(1) : $cursor->addDays(1)) {
            $key      = $cursor->format($monthly ? 'Y-m' : 'Y-m-d');
            $result[] = ['key' => $key, 'count' => $counts[$key] ?? 0];
        }

        return $result;
    }

    /**
     * 기간 내 인기 페이지 상위 $limit건.
     *
     * @return list<array{uri: string, count: int}>
     */
    public function topPagesBetween(Time $start, Time $end, int $limit = 10): array
    {
        $rows = $this->select('uri, COUNT(*) as count')
            ->where('created_at >=', $start->toDateTimeString())
            ->where('created_at <', $end->toDateTimeString())
            ->groupBy('uri')
            ->orderBy('count', 'DESC')
            ->limit($limit)
            ->findAll();

        return array_map(
            static fn (array $row): array => ['uri' => (string) $row['uri'], 'count' => (int) $row['count']],
            $rows,
        );
    }

    /**
     * 기간 내 유입 경로(referer 호스트) 상위 $limit건. referer 없음은 "직접 접속"으로 묶는다.
     * $excludeHost(자기 사이트) 에서 온 내부 이동은 유입이 아니므로 제외한다.
     *
     * @return list<array{host: string, count: int}>
     */
    public function topReferrers(Time $start, Time $end, string $excludeHost = '', int $limit = 10): array
    {
        $host = "SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(referer, '://', -1), '/', 1), ':', 1)";

        $builder = $this->db->table($this->table)
            ->select("COALESCE(NULLIF({$host}, ''), '') as host, COUNT(*) as count", false)
            ->where('created_at >=', $start->toDateTimeString())
            ->where('created_at <', $end->toDateTimeString());

        if ($excludeHost !== '') {
            $builder->groupStart()
                ->where("{$host} != " . $this->db->escape($excludeHost), null, false)
                ->orWhere('referer IS NULL')
                ->groupEnd();
        }

        $rows = $builder->groupBy('host')->orderBy('count', 'DESC')->limit($limit)->get()->getResultArray();

        return array_map(
            static fn (array $row): array => [
                'host'  => (string) $row['host'] === '' ? '직접 접속' : (string) $row['host'],
                'count' => (int) $row['count'],
            ],
            $rows,
        );
    }

    /**
     * 검색엔진 referer 에서 검색어를 뽑는다. 검색엔진이 아니거나 검색어가 없으면 null.
     * (구글은 보통 검색어를 넘기지 않아 대부분 null 이다.)
     *
     * @return array{engine: string, keyword: string}|null
     */
    public static function extractSearchKeyword(string $referer): ?array
    {
        $parts = parse_url($referer);
        $host  = strtolower((string) ($parts['host'] ?? ''));

        foreach (self::SEARCH_ENGINES as $suffix => [$engine, $param]) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                parse_str((string) ($parts['query'] ?? ''), $query);
                $keyword = trim((string) ($query[$param] ?? ''));

                return $keyword === '' ? null : ['engine' => $engine, 'keyword' => mb_substr($keyword, 0, 100)];
            }
        }

        return null;
    }

    /**
     * 기간 내 검색엔진 유입 검색어 상위 $limit건.
     *
     * @return list<array{engine: string, keyword: string, count: int}>
     */
    public function topSearchKeywords(Time $start, Time $end, int $limit = 10): array
    {
        $builder = $this->db->table($this->table)
            ->select('referer, COUNT(*) as count')
            ->where('created_at >=', $start->toDateTimeString())
            ->where('created_at <', $end->toDateTimeString())
            ->groupStart();

        foreach (array_keys(self::SEARCH_ENGINES) as $i => $suffix) {
            $i === 0 ? $builder->like('referer', $suffix) : $builder->orLike('referer', $suffix);
        }
        $rows = $builder->groupEnd()->groupBy('referer')->limit(5000)->get()->getResultArray();

        $counts = [];

        foreach ($rows as $row) {
            $found = self::extractSearchKeyword((string) $row['referer']);
            if ($found === null) {
                continue;
            }
            $key          = $found['engine'] . "\0" . mb_strtolower($found['keyword']);
            $counts[$key] = ($counts[$key] ?? ['engine' => $found['engine'], 'keyword' => $found['keyword'], 'count' => 0]);
            $counts[$key]['count'] += (int) $row['count'];
        }

        usort($counts, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($counts, 0, $limit);
    }

    public function countOnDate(string $date): int
    {
        $start = Time::parse($date);

        return $this->countBetween($start, $start->addDays(1));
    }

    /**
     * 특정 날짜의 방문 로그 전체 컬럼 (최신순).
     *
     * @return list<array<string, mixed>>
     */
    public function visitsOnDate(string $date, int $limit, int $offset): array
    {
        $start = Time::parse($date);

        return $this->betweenQuery($start, $start->addDays(1))
            ->orderBy('id', 'DESC')
            ->findAll($limit, $offset);
    }

    private function betweenQuery(Time $start, Time $end): static
    {
        return $this->where('created_at >=', $start->toDateTimeString())
            ->where('created_at <', $end->toDateTimeString());
    }
}
