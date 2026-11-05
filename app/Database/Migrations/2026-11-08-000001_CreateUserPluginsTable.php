<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 사용자별 플러그인 보유 권한.
 *
 * 사용자·플러그인당 한 행이다. 재구독은 기존 행을 갱신한다 —
 * 구매 이력은 4단계 payments 테이블의 몫이고, 이 테이블은 매 요청 조회되므로
 * "지금 이 사람이 무엇을 쓸 수 있나"만 답해야 한다.
 *
 * status 에 expired 가 없는 것은 의도적이다. 만료는 expires_at 으로 계산한다
 * (Entitlement::accessLevel).
 */
class CreateUserPluginsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'plugin_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
            ],
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'active',
            ],
            // 시각 컬럼은 전부 TIMESTAMP — 전역 타임존 규약
            'purchased_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'expires_at'   => ['type' => 'TIMESTAMP', 'null' => true],
            'cancelled_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'created_at'   => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at'   => ['type' => 'TIMESTAMP', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['user_id', 'plugin_key'], 'uniq_user_plugins_user_id_plugin_key');
        $this->forge->addKey('plugin_key', false, false, 'idx_user_plugins_plugin_key');
        $this->forge->createTable('user_plugins');
    }

    public function down(): void
    {
        $this->forge->dropTable('user_plugins');
    }
}
