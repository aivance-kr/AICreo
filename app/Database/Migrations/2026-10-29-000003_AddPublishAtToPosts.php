<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPublishAtToPosts extends Migration
{
    public function up(): void
    {
        // 예약 발행 시각 — NULL 이면 예약 글이 아니다. 시각이 지나면 posts:publish-scheduled 가 공개 처리하고 비운다
        $this->forge->addColumn('posts', [
            'publish_at' => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null, 'after' => 'is_active'],
        ]);
        $this->db->query('ALTER TABLE `posts` ADD INDEX `idx_posts_publish_at` (`publish_at`)');
    }

    public function down(): void
    {
        $this->forge->dropColumn('posts', 'publish_at');
    }
}
