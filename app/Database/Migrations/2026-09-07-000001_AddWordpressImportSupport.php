<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 워드프레스(WXR) 이관 지원 — 소스 ID 컬럼 + 리다이렉트 테이블.
 *
 * wp_*_id 컬럼은 nullable unique — 이관 데이터만 채워지고 관리자가 직접 만든
 * 네이티브 콘텐츠는 항상 NULL이라 서로 절대 섞이지 않는다. 재실행 시 이 값으로
 * upsert 판별(idempotent) 및 롤백(해당 컬럼 NOT NULL 행만 삭제) 기준으로 쓴다.
 */
class AddWordpressImportSupport extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('boards', [
            'wp_term_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'id'],
        ]);
        $this->db->query('ALTER TABLE `boards` ADD UNIQUE `uniq_boards_wp_term_id` (`wp_term_id`)');

        $this->forge->addColumn('posts', [
            'wp_post_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'id'],
        ]);
        $this->db->query('ALTER TABLE `posts` ADD UNIQUE `uniq_posts_wp_post_id` (`wp_post_id`)');

        $this->forge->addColumn('pages', [
            'wp_post_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'id'],
        ]);
        $this->db->query('ALTER TABLE `pages` ADD UNIQUE `uniq_pages_wp_post_id` (`wp_post_id`)');

        $this->forge->addColumn('media', [
            'wp_attachment_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'id'],
        ]);
        $this->db->query('ALTER TABLE `media` ADD UNIQUE `uniq_media_wp_attachment_id` (`wp_attachment_id`)');

        $this->forge->addColumn('post_comments', [
            'wp_comment_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'id'],
        ]);
        $this->db->query('ALTER TABLE `post_comments` ADD UNIQUE `uniq_post_comments_wp_comment_id` (`wp_comment_id`)');

        // 이관 전 구주소 → 새 주소 리다이렉트 매핑 (SEO 보존용, 영구 유지 — contract 대상 아님)
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'old_path'    => ['type' => 'VARCHAR', 'constraint' => 500],
            'new_path'    => ['type' => 'VARCHAR', 'constraint' => 500],
            'status_code' => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 301],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('old_path');
        $this->forge->createTable('redirects');
    }

    public function down(): void
    {
        $this->forge->dropTable('redirects', true);
        $this->forge->dropColumn('post_comments', 'wp_comment_id');
        $this->forge->dropColumn('media', 'wp_attachment_id');
        $this->forge->dropColumn('pages', 'wp_post_id');
        $this->forge->dropColumn('posts', 'wp_post_id');
        $this->forge->dropColumn('boards', 'wp_term_id');
    }
}
