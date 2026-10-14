<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\PageVisitModel;
use CodeIgniter\I18n\Time;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class PageVisitModelTest extends DatabaseTestCase
{
    private PageVisitModel $visitModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->visitModel = new PageVisitModel();
    }

    public function testLogVisitInsertsRow(): void
    {
        $this->visitModel->logVisit('/board/notice', '127.0.0.1', 'Mozilla/5.0', 'https://example.com');

        $row = $this->visitModel->first();
        $this->assertSame('/board/notice', $row['uri']);
        $this->assertSame('127.0.0.1', $row['ip_address']);
        $this->assertSame('Mozilla/5.0', $row['user_agent']);
        $this->assertSame('https://example.com', $row['referer']);
    }

    public function testCountSinceOnlyCountsRowsAfterGivenTime(): void
    {
        $this->visitModel->logVisit('/', '127.0.0.1', null, null);
        $this->db->table('page_visits')->insert(['uri' => '/', 'ip_address' => '127.0.0.2', 'created_at' => '2000-01-01 00:00:00']);

        $this->assertSame(1, $this->visitModel->countSince(Time::now()->subDays(1)));
    }

    public function testCountUniqueIpsSinceCountsDistinctIps(): void
    {
        $this->visitModel->logVisit('/', '127.0.0.1', null, null);
        $this->visitModel->logVisit('/about', '127.0.0.1', null, null);
        $this->visitModel->logVisit('/', '127.0.0.2', null, null);

        $this->assertSame(2, $this->visitModel->countUniqueIpsSince(Time::now()->subDays(1)));
    }

    public function testDailyCountsFillsMissingDaysWithZero(): void
    {
        $this->visitModel->logVisit('/', '127.0.0.1', null, null);

        $result = $this->visitModel->dailyCounts(3);

        $this->assertCount(3, $result);
        $this->assertSame(Time::now()->format('Y-m-d'), $result[2]['date']);
        $this->assertSame(1, $result[2]['count']);
        $this->assertSame(0, $result[0]['count']);
    }

    public function testTopPagesOrdersByVisitCountDescending(): void
    {
        $this->visitModel->logVisit('/popular', '127.0.0.1', null, null);
        $this->visitModel->logVisit('/popular', '127.0.0.2', null, null);
        $this->visitModel->logVisit('/rare', '127.0.0.1', null, null);

        $result = $this->visitModel->topPages(30, 10);

        $this->assertSame('/popular', $result[0]['uri']);
        $this->assertSame(2, $result[0]['count']);
        $this->assertSame('/rare', $result[1]['uri']);
    }

    public function testPeriodRangeParsesEachPeriodAndRejectsInvalid(): void
    {
        $day = PageVisitModel::periodRange('day', '2026-10-14');
        $this->assertSame('2026-10-14', $day['start']->format('Y-m-d'));
        $this->assertSame('2026-10-15', $day['end']->format('Y-m-d'));

        $week = PageVisitModel::periodRange('week', '2026-W42');
        $this->assertSame('2026-10-12', $week['start']->format('Y-m-d'));
        $this->assertSame('2026-10-19', $week['end']->format('Y-m-d'));

        $month = PageVisitModel::periodRange('month', '2026-12');
        $this->assertSame('2027-01-01', $month['end']->format('Y-m-d'));

        $year = PageVisitModel::periodRange('year', '2026');
        $this->assertSame('2027-01-01', $year['end']->format('Y-m-d'));

        $this->assertNull(PageVisitModel::periodRange('day', '2026-13-45'));
        $this->assertNull(PageVisitModel::periodRange('month', 'abc'));
        $this->assertNull(PageVisitModel::periodRange('quarter', '2026'));
    }

    public function testBucketCountsGroupsByDayAndMonth(): void
    {
        $this->insertVisit('/', '2026-10-01 10:00:00');
        $this->insertVisit('/', '2026-10-01 11:00:00');
        $this->insertVisit('/', '2026-11-05 09:00:00');

        $range   = PageVisitModel::periodRange('year', '2026');
        $monthly = $this->visitModel->bucketCounts($range['start'], $range['end'], true);
        $this->assertCount(12, $monthly);
        $this->assertSame(['key' => '2026-10', 'count' => 2], $monthly[9]);
        $this->assertSame(1, $monthly[10]['count']);

        $range = PageVisitModel::periodRange('month', '2026-10');
        $daily = $this->visitModel->bucketCounts($range['start'], $range['end'], false);
        $this->assertCount(31, $daily);
        $this->assertSame(2, $daily[0]['count']);
    }

    public function testCountsAndTopPagesBetweenRespectRange(): void
    {
        $this->insertVisit('/a', '2026-10-01 10:00:00', '1.1.1.1');
        $this->insertVisit('/a', '2026-10-01 11:00:00', '1.1.1.1');
        $this->insertVisit('/b', '2026-10-02 00:00:00', '2.2.2.2');

        $range = PageVisitModel::periodRange('day', '2026-10-01');
        $this->assertSame(2, $this->visitModel->countBetween($range['start'], $range['end']));
        $this->assertSame(1, $this->visitModel->countUniqueIpsBetween($range['start'], $range['end']));
        $this->assertSame([['uri' => '/a', 'count' => 2]], $this->visitModel->topPagesBetween($range['start'], $range['end']));
    }

    public function testTopReferrersGroupsByHostAndExcludesOwnHost(): void
    {
        $this->insertVisit('/', '2026-10-01 10:00:00', '1.1.1.1', 'https://www.google.com/search?q=x');
        $this->insertVisit('/', '2026-10-01 10:01:00', '1.1.1.2', 'https://www.google.com/');
        $this->insertVisit('/', '2026-10-01 10:02:00', '1.1.1.3', 'https://creo.test/about');
        $this->insertVisit('/', '2026-10-01 10:03:00', '1.1.1.4', null);

        $range  = PageVisitModel::periodRange('day', '2026-10-01');
        $result = $this->visitModel->topReferrers($range['start'], $range['end'], 'creo.test');

        $this->assertSame([
            ['host' => 'www.google.com', 'count' => 2],
            ['host' => '직접 접속', 'count' => 1],
        ], $result);
    }

    public function testVisitsOnDateReturnsAllColumnsNewestFirst(): void
    {
        $this->insertVisit('/first', '2026-10-01 10:00:00', '1.1.1.1', 'https://x.com/', 'UA-1');
        $this->insertVisit('/second', '2026-10-01 11:00:00');
        $this->insertVisit('/other', '2026-10-02 11:00:00');

        $rows = $this->visitModel->visitsOnDate('2026-10-01', 10, 0);

        $this->assertSame(2, $this->visitModel->countOnDate('2026-10-01'));
        $this->assertSame('/second', $rows[0]['uri']);
        $this->assertSame('UA-1', $rows[1]['user_agent']);
        $this->assertSame('https://x.com/', $rows[1]['referer']);
    }

    private function insertVisit(
        string $uri,
        string $createdAt,
        string $ip = '127.0.0.1',
        ?string $referer = null,
        ?string $userAgent = null,
    ): void {
        $this->db->table('page_visits')->insert([
            'uri'        => $uri,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'referer'    => $referer,
            'created_at' => $createdAt,
        ]);
    }
}
