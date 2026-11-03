<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminLogModel;
use App\Models\BlockedIpModel;
use App\Models\PageModel;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class AdminLogTest extends AdminTestCase
{
    public function testSettingsSaveLogsKeysButNeverValues(): void
    {
        $this->withSession($this->adminSession)->post('admin/settings/general', [
            'site_name' => '로그테스트', 'smtp_pass' => 'S3cret-Value!',
        ]);

        $row = (new AdminLogModel())->where('target_type', 'setting')->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row['user_id']);
        $this->assertStringContainsString('smtp_pass', (string) $row['summary']);
        $this->assertStringNotContainsString('S3cret-Value!', (string) $row['summary']);
        $this->assertStringNotContainsString('로그테스트', (string) $row['summary']);
    }

    public function testPageDeleteIsLogged(): void
    {
        $id = (int) (new PageModel())->insert(['slug' => 'log-test', 'title' => '삭제될 페이지', 'content' => '', 'status' => 'published']);

        $this->withSession($this->adminSession)->post("admin/pages/{$id}/delete");

        $row = (new AdminLogModel())->where('target_type', 'page')->first();
        $this->assertSame('delete', $row['action']);
        $this->assertSame('삭제될 페이지', $row['summary']);
    }

    public function testUnblockIsLogged(): void
    {
        $model = new BlockedIpModel();
        $model->block('1.2.3.4', BlockedIpModel::REASON_MANUAL);
        $id = (int) $model->where('ip_address', '1.2.3.4')->first()['id'];

        $this->withSession($this->adminSession)->post("admin/blocked-ips/{$id}/delete");

        $row = (new AdminLogModel())->where('target_type', 'blocked_ip')->first();
        $this->assertSame('unblock', $row['action']);
        $this->assertSame('1.2.3.4', $row['summary']);
    }

    public function testListShowsAndFiltersLogsEscaped(): void
    {
        $model = new AdminLogModel();
        $model->record('delete', 'post', 5, '<script>alert(1)</script>', 1, '127.0.0.1');
        $model->record('update', 'user', 6, '회원 수정', 1, '127.0.0.1');

        $all = $this->withSession($this->adminSession)->get('admin/logs');
        $all->assertStatus(200);
        $this->assertStringNotContainsString('<script>alert(1)</script>', (string) $all->getBody());
        $all->assertSee('회원 수정');

        $filtered = $this->withSession($this->adminSession)->get('admin/logs?target_type=user');
        $filtered->assertSee('회원 수정');
        $filtered->assertDontSee('alert(1)');
    }

    public function testNonAdminCannotSeeLogs(): void
    {
        $this->withSession(['user_id' => 2, 'user_role' => 'member'])->get('admin/logs')->assertRedirect();
    }
}
