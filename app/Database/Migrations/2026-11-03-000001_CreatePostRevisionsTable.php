<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePostRevisionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'post_id' => ['type' => 'INT', 'unsigned' => true],
            // 저장한 사람. 비회원이 고친 글이면 NULL
            'user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'content'    => ['type' => 'LONGTEXT', 'null' => true],
            'meta_desc'  => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['post_id', 'id'], false, false, 'idx_post_revisions_post_id');
        $this->forge->createTable('post_revisions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('post_revisions', true);
    }
}
