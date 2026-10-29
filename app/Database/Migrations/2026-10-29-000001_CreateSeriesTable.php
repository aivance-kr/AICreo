<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSeriesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 200],
            'description' => ['type' => 'TEXT', 'null' => true],
            'status'      => ['type' => 'ENUM', 'constraint' => ['ongoing', 'completed'], 'default' => 'ongoing'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('series', true);

        // 한 글은 최대 한 시리즈에만 속한다. 외래 키·유니크는 일부러 걸지 않는다
        // (순서 맞바꿈이 제약에 걸리고, 시리즈 삭제 시 SET NULL 은 모델에서 캐시 무효화와 함께 처리).
        $this->forge->addColumn('posts', [
            'series_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'series_order' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
        ]);
        $this->db->query('CREATE INDEX idx_posts_series ON posts (series_id, series_order)');
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX idx_posts_series ON posts');
        $this->forge->dropColumn('posts', ['series_id', 'series_order']);
        $this->forge->dropTable('series', true);
    }
}
