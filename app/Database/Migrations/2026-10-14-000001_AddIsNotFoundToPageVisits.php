<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsNotFoundToPageVisits extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('page_visits', [
            'is_not_found' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 0, 'after' => 'referer'],
        ]);
        $this->db->query('CREATE INDEX idx_page_visits_not_found_created_at ON page_visits (is_not_found, created_at)');
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX idx_page_visits_not_found_created_at ON page_visits');
        $this->forge->dropColumn('page_visits', 'is_not_found');
    }
}
