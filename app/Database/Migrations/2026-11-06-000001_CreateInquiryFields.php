<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInquiryFields extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'    => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'label' => ['type' => 'VARCHAR', 'constraint' => 100],
            // text | textarea | select | checkbox | file
            'field_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'text'],
            // 선택·체크박스 선택지 (줄바꿈 구분)
            'options'     => ['type' => 'TEXT', 'null' => true],
            'is_required' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order'  => ['type' => 'INT', 'default' => 0],
            'created_at'  => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['is_active', 'sort_order'], false, false, 'idx_inquiry_fields_active_sort');
        $this->forge->createTable('inquiry_fields', true);

        // 제출된 추가 항목(JSON). 기존 문의는 NULL 로 남아 그대로 호환된다
        $this->forge->addColumn('inquiries', [
            'extra_data' => ['type' => 'TEXT', 'null' => true, 'after' => 'message'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('inquiries', 'extra_data');
        $this->forge->dropTable('inquiry_fields', true);
    }
}
