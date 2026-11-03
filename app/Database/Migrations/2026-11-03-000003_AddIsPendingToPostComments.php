<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsPendingToPostComments extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('post_comments', [
            'is_pending' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'after'      => 'is_active',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('post_comments', 'is_pending');
    }
}
