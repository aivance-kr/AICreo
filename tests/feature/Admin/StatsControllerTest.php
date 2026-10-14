<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\PageVisitModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class StatsControllerTest extends AdminTestCase
{
    public function testAdminCanViewStatsPage(): void
    {
        (new PageVisitModel())->logVisit('/board/notice', '127.0.0.1', 'Mozilla/5.0', null);

        $result = $this->withSession($this->adminSession)->get('admin/stats');

        $result->assertStatus(200);
        $result->assertSee('접속 통계');
        $result->assertSee('/board/notice');
    }

    public function testMemberCannotAccessStatsPage(): void
    {
        $this->withSession($this->memberSession)->get('admin/stats')->assertStatus(302);
    }

    public function testPeriodSearchShowsReferrersAndLinksBarsToDayDetail(): void
    {
        $this->db->table('page_visits')->insert([
            'uri'     => '/hello', 'ip_address' => '9.9.9.9', 'user_agent' => 'UA-X',
            'referer' => 'https://www.naver.com/x', 'created_at' => '2026-10-03 12:00:00',
        ]);

        $result = $this->withSession($this->adminSession)->get('admin/stats?period=month&value=2026-10');

        $result->assertStatus(200);
        $result->assertSee('www.naver.com');
        $result->assertSee('admin/stats/day/2026-10-03');
    }

    public function testInvalidPeriodFallsBackToDefaultView(): void
    {
        $this->withSession($this->adminSession)->get('admin/stats?period=month&value=zzz')->assertStatus(200);
    }

    public function testDayDetailShowsEveryCollectedField(): void
    {
        $this->db->table('page_visits')->insert([
            'uri'     => '/hello', 'ip_address' => '9.9.9.9', 'user_agent' => 'UA-X',
            'referer' => 'https://www.naver.com/x', 'created_at' => '2026-10-03 12:34:56',
        ]);

        $result = $this->withSession($this->adminSession)->get('admin/stats/day/2026-10-03');

        $result->assertStatus(200);

        foreach (['/hello', '9.9.9.9', 'UA-X', 'https://www.naver.com/x', '12:34:56'] as $text) {
            $result->assertSee($text);
        }
    }

    public function testDayDetailRejectsInvalidDate(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->withSession($this->adminSession)->get('admin/stats/day/not-a-date');
    }
}
