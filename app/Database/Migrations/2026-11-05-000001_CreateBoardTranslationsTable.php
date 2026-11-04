<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 다국어(i18n) 후속: 게시판 이름·설명의 언어별 번역.
 * 기본 언어는 `boards.name`/`description` 을 그대로 쓰고, 이 표에는 그 밖의 언어만 둔다.
 * 번역이 없는(또는 이름이 빈) 언어는 기본 언어 값으로 대신한다.
 */
class CreateBoardTranslationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'board_id'    => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'locale'      => ['type' => 'VARCHAR', 'constraint' => 8],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['board_id', 'locale'], 'uniq_board_translations_board_id_locale');
        $this->forge->createTable('board_translations');
    }

    public function down(): void
    {
        $this->forge->dropTable('board_translations', true);
    }
}
