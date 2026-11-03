<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDeletedAtToPages extends Migration
{
    public function up(): void
    {
        // 소프트 삭제(휴지통) — posts·post_comments 와 같은 DATETIME NULL. NULL 이면 살아 있는 페이지
        $this->forge->addColumn('pages', [
            'deleted_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'updated_at'],
        ]);
        $this->db->query('ALTER TABLE pages ADD INDEX idx_pages_deleted_at (deleted_at)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE pages DROP INDEX idx_pages_deleted_at');
        $this->forge->dropColumn('pages', 'deleted_at');
    }
}
