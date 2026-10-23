<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSignaturesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'     => ['type' => 'INT', 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'content'     => ['type' => 'TEXT'],
            'is_selected' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'  => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at'  => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id', false, false, 'idx_signatures_user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('signatures');

        // 글 작성 시점의 서명 HTML 스냅샷 — 서명을 바꿔도 기존 글은 변하지 않는다
        $this->forge->addColumn('posts', [
            'signature' => ['type' => 'TEXT', 'null' => true, 'after' => 'content'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('posts', 'signature');
        $this->forge->dropTable('signatures');
    }
}
