<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTagsToPosts extends Migration
{
    public function up(): void
    {
        // 쉼표로 구분한 태그 목록 (정규화는 PostModel::normalizeTags)
        $this->forge->addColumn('posts', [
            'tags' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'content'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('posts', 'tags');
    }
}
