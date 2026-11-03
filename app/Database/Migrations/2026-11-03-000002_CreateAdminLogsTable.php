<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAdminLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            // 동작한 관리자. 자동 차단처럼 사람이 아닌 동작이면 NULL
            'user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            // create / update / delete 등
            'action' => ['type' => 'VARCHAR', 'constraint' => 30],
            // post / page / setting / user / blocked_ip 등
            'target_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'target_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            // 사람이 읽는 요약. 비밀번호·토큰 같은 값은 넣지 않는다
            'summary'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('created_at', false, false, 'idx_admin_logs_created_at');
        $this->forge->addKey('user_id', false, false, 'idx_admin_logs_user_id');
        $this->forge->addKey(['target_type', 'action'], false, false, 'idx_admin_logs_target_type_action');
        $this->forge->createTable('admin_logs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('admin_logs', true);
    }
}
