<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\SettingModel;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class SettingTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        cache()->delete('site_settings');
    }

    public function testAdminSavesSettings(): void
    {
        $result = $this->withSession($this->adminSession)->post('admin/settings/general', [
            'site_name' => '내 회사',
            'site_desc' => '새 설명',
        ]);

        $result->assertRedirectTo('/admin/settings/general');

        $map = (new SettingModel())->getAllAsMap();
        $this->assertSame('내 회사', $map['site_name']);
        $this->assertSame('새 설명', $map['site_desc']);
    }

    public function testGeneralTabRendersMediaPickerControlsForImageFields(): void
    {
        $result = $this->withSession($this->adminSession)->get('admin/settings/general');

        $result->assertStatus(200);
        $result->assertSee('미디어에서 선택');
        $result->assertSee('직접 업로드');
    }

    public function testAdminCanToggleLatestHomeNotices(): void
    {
        $result = $this->withSession($this->adminSession)->get('admin/settings/general');

        $result->assertStatus(200);
        $result->assertSee('홈 최신 공지사항 표시');

        $result = $this->withSession($this->adminSession)->post('admin/settings/general', [
            'home_show_latest_notices' => '0',
        ]);

        $result->assertRedirectTo('/admin/settings/general');
        $this->assertSame('0', (new SettingModel())->getAllAsMap()['home_show_latest_notices']);
    }
}
