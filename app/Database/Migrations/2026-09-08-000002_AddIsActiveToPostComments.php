<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsActiveToPostComments extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('post_comments', [
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'after'      => 'author_password',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('post_comments', 'is_active');
    }
}
