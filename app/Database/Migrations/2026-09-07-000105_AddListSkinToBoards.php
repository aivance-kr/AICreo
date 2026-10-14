<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddListSkinToBoards extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('boards', [
            'list_skin' => [
                'type'       => 'ENUM',
                'constraint' => ['list', 'blog', 'gallery'],
                'default'    => 'list',
                'after'      => 'posts_per_page',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('boards', 'list_skin');
    }
}
