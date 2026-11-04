<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 다국어(i18n) 2단계: 페이지 슬러그 유일키를 (locale, slug) 로 바꿔 언어별로 같은 슬러그를 쓸 수 있게 한다.
 */
class PageSlugUniquePerLocale extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE `pages` DROP INDEX `slug`, ADD UNIQUE INDEX `uniq_pages_locale_slug` (`locale`, `slug`)');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `pages` DROP INDEX `uniq_pages_locale_slug`, ADD UNIQUE INDEX `slug` (`slug`)');
    }
}
