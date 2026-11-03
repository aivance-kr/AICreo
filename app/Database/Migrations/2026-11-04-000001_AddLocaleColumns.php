<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 다국어(i18n) 1단계: 글·페이지·메뉴에 언어 컬럼, 글·페이지에 번역 묶음 컬럼을 더한다.
 * 기존 행은 모두 기본 언어(ko), 묶음 없음(NULL)이라 동작은 그대로다.
 */
class AddLocaleColumns extends Migration
{
    public function up(): void
    {
        $locale = ['type' => 'VARCHAR', 'constraint' => 8, 'null' => false, 'default' => 'ko'];
        $group  = ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'default' => null];

        $this->forge->addColumn('posts', ['locale' => $locale + ['after' => 'board_id'], 'translation_group_id' => $group + ['after' => 'locale']]);
        $this->forge->addColumn('pages', ['locale' => $locale + ['after' => 'id'], 'translation_group_id' => $group + ['after' => 'locale']]);
        $this->forge->addColumn('menus', ['locale' => $locale + ['after' => 'id']]);

        $this->db->query('ALTER TABLE `posts` ADD INDEX `idx_posts_translation_group_id` (`translation_group_id`)');
        $this->db->query('ALTER TABLE `pages` ADD INDEX `idx_pages_translation_group_id` (`translation_group_id`)');
        $this->db->query('ALTER TABLE `posts` ADD INDEX `idx_posts_locale` (`locale`)');
        $this->db->query('ALTER TABLE `menus` ADD INDEX `idx_menus_locale` (`locale`)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `posts` DROP INDEX `idx_posts_translation_group_id`, DROP INDEX `idx_posts_locale`');
        $this->db->query('ALTER TABLE `pages` DROP INDEX `idx_pages_translation_group_id`');
        $this->db->query('ALTER TABLE `menus` DROP INDEX `idx_menus_locale`');

        $this->forge->dropColumn('posts', ['locale', 'translation_group_id']);
        $this->forge->dropColumn('pages', ['locale', 'translation_group_id']);
        $this->forge->dropColumn('menus', 'locale');
    }
}
