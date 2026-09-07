<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AllowNullMenuUrl extends Migration
{
    public function up(): void
    {
        // 하위 메뉴만 있는 상위 메뉴는 링크가 없을 수 있다.
        $this->forge->modifyColumn('menus', [
            'url' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
        ]);
    }

    public function down(): void
    {
        $this->db->table('menus')->where('url', null)->update(['url' => '']);
        $this->forge->modifyColumn('menus', [
            'url' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => false, 'default' => ''],
        ]);
    }
}
