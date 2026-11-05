<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 플러그인별 운영 설정값. 매니페스트 settings[] 에 선언된 key => 값 JSON.
 */
class AddSettingsToPlugins extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('plugins', [
            'settings' => ['type' => 'JSON', 'null' => true, 'after' => 'manifest'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('plugins', 'settings');
    }
}
