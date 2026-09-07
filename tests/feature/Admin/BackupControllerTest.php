<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class BackupControllerTest extends AdminTestCase
{
    public function testAdminCanViewBackupManagementPage(): void
    {
        $result = $this->withSession($this->adminSession)->get('admin/backup');

        $result->assertStatus(200);
        $result->assertSee('백업 생성');
        $result->assertSee('백업 복원');
    }

    public function testRestoreRequiresExactConfirmation(): void
    {
        $this->withSession($this->adminSession)->post('admin/backup/restore', ['confirmation' => '취소'])->assertRedirectTo('/admin/backup');
    }

    public function testMemberCannotAccessBackupManagement(): void
    {
        $this->withSession($this->memberSession)->get('admin/backup')->assertRedirect();
    }
}
