<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\PageVisitModel;
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
}
