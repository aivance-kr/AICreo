<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 전 페이지 <head> 삽입 코드 설정 키 추가 (구글 애드센스 자동광고 등).
 */
class AddHeadCodeSetting extends Migration
{
    private const KEY = 'head_code';

    public function up(): void
    {
        $exists = $this->db->table('settings')->where('key', self::KEY)->get()->getRow();
        if ($exists) {
            return;
        }

        $this->db->table('settings')->insert([
            'group'      => 'seo',
            'key'        => self::KEY,
            'value'      => '',
            'label'      => 'head 삽입 코드',
            'type'       => 'textarea',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function down(): void
    {
        $this->db->table('settings')->where('key', self::KEY)->delete();
    }
}
