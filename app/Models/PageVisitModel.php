<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

class PageVisitModel extends Model
{
    protected $table         = 'page_visits';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $allowedFields = ['uri', 'ip_address', 'user_agent', 'referer'];

    public function logVisit(string $uri, string $ipAddress, ?string $userAgent, ?string $referer): void
    {
        $this->insert([
            'uri'        => $uri,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'referer'    => $referer,
        ]);
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
}
