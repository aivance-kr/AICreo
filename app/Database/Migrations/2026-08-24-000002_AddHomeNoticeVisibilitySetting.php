<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddHomeNoticeVisibilitySetting extends Migration
{
    public function up(): void
    {
        $exists = $this->db->table('settings')
            ->where('key', 'home_show_latest_notices')
            ->get()
            ->getRow();

        if ($exists) {
            return;
        }

        $this->db->table('settings')->insert([
            'group'      => 'general',
            'key'        => 'home_show_latest_notices',
            'value'      => '1',
            'label'      => '홈 최신 공지사항 표시',
            'type'       => 'boolean',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function down(): void
    {
        $this->db->table('settings')->where('key', 'home_show_latest_notices')->delete();
    }
}
