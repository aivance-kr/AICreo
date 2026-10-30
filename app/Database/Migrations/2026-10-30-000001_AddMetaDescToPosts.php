<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMetaDescToPosts extends Migration
{
    public function up(): void
    {
        // 운영자가 직접 쓰거나 AI 제안으로 채우는 메타 설명 — NULL 이면 본문 앞부분(post_excerpt)을 쓴다
        $this->forge->addColumn('posts', [
            'meta_desc' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true, 'after' => 'tags'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('posts', 'meta_desc');
    }
}
