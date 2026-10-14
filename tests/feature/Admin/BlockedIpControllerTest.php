<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BlockedIpModel;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class BlockedIpControllerTest extends AdminTestCase
{
    public function testAdminSeesBlockedIpsButNotExpiredOnes(): void
    {
        $model = new BlockedIpModel();
        $model->block('1.2.3.4', BlockedIpModel::REASON_MANUAL);
        $model->insert(['ip_address' => '5.6.7.8', 'reason' => BlockedIpModel::REASON_AUTO, 'blocked_until' => '2020-01-01 00:00:00']);

        $result = $this->withSession($this->adminSession)->get('admin/blocked-ips');

        $result->assertStatus(200);
        $result->assertSee('1.2.3.4');
        $result->assertDontSee('5.6.7.8');
    }

    public function testSearchFiltersByIp(): void
    {
        $model = new BlockedIpModel();
        $model->block('1.2.3.4', BlockedIpModel::REASON_MANUAL);
        $model->block('9.9.9.9', BlockedIpModel::REASON_AUTO);

        $result = $this->withSession($this->adminSession)->get('admin/blocked-ips?q=9.9');

        $result->assertSee('9.9.9.9');
        $result->assertDontSee('1.2.3.4');
    }

    public function testAdminCanUnblock(): void
    {
        $model = new BlockedIpModel();
        $model->block('1.2.3.4', BlockedIpModel::REASON_MANUAL);
        $id = (int) $model->where('ip_address', '1.2.3.4')->first()['id'];

        $this->withSession($this->adminSession)->post("admin/blocked-ips/{$id}/delete");

        $this->assertFalse($model->isBlocked('1.2.3.4'));
    }

    public function testMemberCannotAccess(): void
    {
        $this->withSession($this->memberSession)->get('admin/blocked-ips')->assertStatus(302);
    }
}
