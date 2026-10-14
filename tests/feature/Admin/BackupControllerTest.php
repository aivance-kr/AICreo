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

    public function testRestoreFromServerRequiresExactConfirmation(): void
    {
        $result = $this->withSession($this->adminSession)->post('admin/backup/restore-server', ['confirmation' => '취소', 'filename' => 'aicreo-backup-20260101-000000-aabbccdd.zip']);

        $result->assertRedirectTo('/admin/backup');
        $result->assertSessionHas('error', '복원 확인 문구가 일치하지 않습니다.');
    }

    public function testRestoreFromServerRejectsUnknownFilename(): void
    {
        $result = $this->withSession($this->adminSession)->post('admin/backup/restore-server', ['confirmation' => '복원', 'filename' => 'aicreo-backup-20260101-000000-aabbccdd.zip']);

        $result->assertRedirectTo('/admin/backup');
        $result->assertSessionHas('error', '백업 파일을 찾을 수 없습니다.');
    }

    public function testMemberCannotAccessBackupManagement(): void
    {
        $this->withSession($this->memberSession)->get('admin/backup')->assertRedirect();
    }
}
