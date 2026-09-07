<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 워드프레스 이관 지원 — board_categories에 wp_term_id 추가.
 *
 * #282(게시판 카테고리 기능) 이후 워드프레스 마이그레이션은 카테고리마다 별도
 * 게시판(boards)을 만들지 않고, 사전에 생성된 대상 게시판 아래 board_categories로
 * 매핑한다. board_id + wp_term_id 조합 unique로 게시판별 재실행 idempotent 보장
 * (관리자가 직접 만든 카테고리는 이 컬럼이 항상 NULL이라 절대 섞이지 않음).
 */
class AddWpTermIdToBoardCategories extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('board_categories', [
            'wp_term_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'board_id'],
        ]);
        $this->db->query('ALTER TABLE `board_categories` ADD UNIQUE `uniq_board_categories_board_wp_term` (`board_id`, `wp_term_id`)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `board_categories` DROP INDEX `uniq_board_categories_board_wp_term`');
        $this->forge->dropColumn('board_categories', 'wp_term_id');
    }
}
