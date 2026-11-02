<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePostViewsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'post_id' => ['type' => 'INT', 'unsigned' => true],
            // 회원은 "u:{id}", 비회원은 "ip:{서버 키 HMAC}" — post_likes.voter_key 와 같은 형식
            'viewer_key' => ['type' => 'VARCHAR', 'constraint' => 80],
            // 이 방문자가 이 글의 조회수를 마지막으로 올린 날짜
            'viewed_on' => ['type' => 'DATE'],
        ]);
        $this->forge->addKey(['post_id', 'viewer_key'], true);
        $this->forge->createTable('post_views');
    }

    public function down(): void
    {
        $this->forge->dropTable('post_views', true);
    }
}
