<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePostPreviewTokensTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'post_id'    => ['type' => 'INT', 'unsigned' => true],
            'token'      => ['type' => 'CHAR', 'constraint' => 64],
            'expires_at' => ['type' => 'TIMESTAMP', 'null' => true],
            // 폐기하면 채운다. 행을 지우지 않아 발급 내역이 남는다
            'revoked_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('token', 'uniq_post_preview_tokens_token');
        $this->forge->addKey(['post_id', 'id'], false, false, 'idx_post_preview_tokens_post_id');
        $this->forge->createTable('post_preview_tokens', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('post_preview_tokens', true);
    }
}
