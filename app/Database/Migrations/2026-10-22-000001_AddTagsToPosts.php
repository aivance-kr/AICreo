<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTagsToPosts extends Migration
{
    public function up(): void
    {
        // 쉼표로 구분한 태그 문자열 (AI 자동 추출)
        $this->forge->addColumn('posts', [
            'tags' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'content'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('posts', 'tags');
    }
}
