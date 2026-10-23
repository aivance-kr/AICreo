<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePostLikesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'post_id' => ['type' => 'INT', 'unsigned' => true],
            // 회원은 "u:{id}", 비회원은 "ip:{IP 해시}" — 원본 IP 는 저장하지 않는다
            'voter_key'  => ['type' => 'VARCHAR', 'constraint' => 80],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['post_id', 'voter_key'], 'uniq_post_likes_post_id_voter_key');
        $this->forge->createTable('post_likes');
    }

    public function down(): void
    {
        $this->forge->dropTable('post_likes', true);
    }
}
