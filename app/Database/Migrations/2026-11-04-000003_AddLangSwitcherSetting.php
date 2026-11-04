<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 다국어(i18n) 5단계: 언어 전환기 노출 설정 (기본 노출, 사용 언어가 둘 이상일 때만 의미가 있다).
 */
class AddLangSwitcherSetting extends Migration
{
    public function up(): void
    {
        if ($this->db->table('settings')->where('key', 'show_lang_switcher')->get()->getRow()) {
            return;
        }

        $this->db->table('settings')->insert([
            'group'      => 'general',
            'key'        => 'show_lang_switcher',
            'value'      => '1',
            'label'      => '언어 전환기 노출',
            'type'       => 'boolean',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function down(): void
    {
        $this->db->table('settings')->where('key', 'show_lang_switcher')->delete();
    }
}
