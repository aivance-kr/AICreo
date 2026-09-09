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
}
