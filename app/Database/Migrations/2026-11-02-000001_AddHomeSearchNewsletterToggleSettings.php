<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 메인 검색창·푸터 새 글 알림 받기 노출 여부 설정 (기본 노출).
 */
class AddHomeSearchNewsletterToggleSettings extends Migration
{
    /**
     * @var list<array{group:string,key:string,label:string}>
     */
    private array $rows = [
        ['group' => 'general', 'key' => 'show_home_search', 'label' => '메인 검색창 노출'],
        ['group' => 'footer', 'key' => 'show_footer_newsletter', 'label' => '푸터 새 글 알림 받기 노출'],
    ];

    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ($this->rows as $row) {
            if ($this->db->table('settings')->where('key', $row['key'])->get()->getRow()) {
                continue;
            }

            $this->db->table('settings')->insert([
                'group'      => $row['group'],
                'key'        => $row['key'],
                'value'      => '1',
                'label'      => $row['label'],
                'type'       => 'boolean',
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $this->db->table('settings')->whereIn('key', array_column($this->rows, 'key'))->delete();
    }
}
