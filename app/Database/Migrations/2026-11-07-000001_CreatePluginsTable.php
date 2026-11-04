<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 플러그인 레지스트리 테이블.
 *
 * 파일시스템(app/Plugins/*)이 "존재 여부"라면 이 테이블은 "운영 상태"다.
 * writable/plugins/active.php 는 status='active' 목록을 부팅용으로 투영한 캐시다.
 *
 * 컬럼명이 key 가 아니라 plugin_key 인 이유: KEY 는 MySQL 예약어다.
 */
class CreatePluginsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'plugin_key'         => ['type' => 'VARCHAR', 'constraint' => 32],
            'name'               => ['type' => 'VARCHAR', 'constraint' => 64],
            'version'            => ['type' => 'VARCHAR', 'constraint' => 20],
            'status'             => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'inactive'],
            'license_type'       => ['type' => 'VARCHAR', 'constraint' => 20],
            'price_onetime'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'price_subscription' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'credit_price_table' => ['type' => 'JSON', 'null' => true],
            'manifest'           => ['type' => 'JSON', 'null' => true],
            // 시각 컬럼은 전부 TIMESTAMP — 전역 타임존 규약(디스크 UTC / 세션 KST)
            'installed_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'created_at'   => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at'   => ['type' => 'TIMESTAMP', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('plugin_key', 'uniq_plugins_plugin_key');
        $this->forge->addKey('status', false, false, 'idx_plugins_status');
        $this->forge->createTable('plugins');
    }

    public function down(): void
    {
        $this->forge->dropTable('plugins');
    }
}
