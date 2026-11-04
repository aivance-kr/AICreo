<?php

declare(strict_types=1);

namespace Plugins\Migrating\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 활성화 시 마이그레이션 실행, 제거 시 rollback 이 실제로 도는지 검증하기 위한
 * 테스트 픽스처. 마이그레이션이 없는 픽스처만으로는 그 경로가 검증되지 않는다.
 */
class CreateMigratingItemsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('migrating_items');
    }

    public function down(): void
    {
        $this->forge->dropTable('migrating_items');
    }
}
