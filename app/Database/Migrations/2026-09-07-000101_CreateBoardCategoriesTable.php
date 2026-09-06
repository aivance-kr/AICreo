<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBoardCategoriesTable extends Migration
{
    public function up(): void
    {
        // 게시판별 카테고리 (게시판마다 별도로 관리)
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'board_id'   => ['type' => 'INT', 'unsigned' => true],
            'slug'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'sort_order' => ['type' => 'INT', 'default' => 0],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('board_id');
        $this->forge->addUniqueKey(['board_id', 'slug']);
        $this->forge->createTable('board_categories');

        // 게시글이 속한 카테고리 (선택사항 — 카테고리 없는 게시판/글도 허용)
        $this->forge->addColumn('posts', [
            'category_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'board_id'],
        ]);
        $this->db->query('ALTER TABLE posts ADD INDEX idx_posts_category_id (category_id)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE posts DROP INDEX idx_posts_category_id');
        $this->forge->dropColumn('posts', 'category_id');
        $this->forge->dropTable('board_categories', true);
    }
}
