<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePageVisitsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'uri'        => ['type' => 'VARCHAR', 'constraint' => 500],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45],
            'user_agent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'referer'    => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('created_at', false, false, 'idx_page_visits_created_at');
        $this->forge->addKey('uri', false, false, 'idx_page_visits_uri');
        $this->forge->createTable('page_visits');
    }

    public function down(): void
    {
        $this->forge->dropTable('page_visits', true);
    }
}
