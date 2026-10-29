<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 한 글이 여러 시리즈에 속할 수 있도록 posts.series_id·series_order 를 post_series 피벗으로 옮긴다.
 */
class CreatePostSeriesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'series_id'    => ['type' => 'INT', 'unsigned' => true],
            'post_id'      => ['type' => 'INT', 'unsigned' => true],
            'series_order' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey(['series_id', 'post_id']);
        $this->forge->addKey('post_id', false, false, 'idx_post_series_post_id');
        $this->forge->createTable('post_series', true);

        $this->db->query('INSERT INTO post_series (series_id, post_id, series_order) SELECT series_id, id, series_order FROM posts WHERE series_id IS NOT NULL');

        $this->db->query('DROP INDEX idx_posts_series ON posts');
        $this->forge->dropColumn('posts', ['series_id', 'series_order']);
    }

    public function down(): void
    {
        $this->forge->addColumn('posts', [
            'series_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'series_order' => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
        ]);
        // 여러 시리즈에 속한 글은 번호가 가장 작은 시리즈 하나만 남는다.
        $this->db->query('UPDATE posts INNER JOIN (SELECT post_id, MIN(series_id) AS series_id FROM post_series GROUP BY post_id) m ON m.post_id = posts.id '
            . 'INNER JOIN post_series ps ON ps.post_id = m.post_id AND ps.series_id = m.series_id '
            . 'SET posts.series_id = ps.series_id, posts.series_order = ps.series_order');
        $this->db->query('CREATE INDEX idx_posts_series ON posts (series_id, series_order)');
        $this->forge->dropTable('post_series', true);
    }
}
