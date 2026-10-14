<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBlockedIpsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'ip_address'    => ['type' => 'VARCHAR', 'constraint' => 45],
            'reason'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'blocked_until' => ['type' => 'DATETIME'],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('ip_address', 'uniq_blocked_ips_ip_address');
        $this->forge->createTable('blocked_ips');
    }

    public function down(): void
    {
        $this->forge->dropTable('blocked_ips', true);
    }
}
