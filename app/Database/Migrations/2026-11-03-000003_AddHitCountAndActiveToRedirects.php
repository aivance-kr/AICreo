<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddHitCountAndActiveToRedirects extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('redirects', [
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 1, 'after' => 'status_code'],
            'hit_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0, 'after' => 'is_active'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('redirects', ['is_active', 'hit_count']);
    }
}
