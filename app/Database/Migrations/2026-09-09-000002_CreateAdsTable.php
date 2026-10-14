<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAdsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'position'   => ['type' => 'ENUM', 'constraint' => ['home_top', 'home_bottom', 'post_top', 'post_bottom'], 'default' => 'home_top'],
            'code'       => ['type' => 'TEXT'],
            'priority'   => ['type' => 'INT', 'default' => 0],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('ads');
    }

    public function down(): void
    {
        $this->forge->dropTable('ads', true);
    }
}
