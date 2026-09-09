<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Daum(카카오) 검색등록 사이트 인증 설정 키 추가 (#345).
 */
class AddDaumVerifySetting extends Migration
{
    private const KEY = 'daum_verify';

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
            'label'      => 'Daum 검색등록 인증',
            'type'       => 'text',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function down(): void
    {
        $this->db->table('settings')->where('key', self::KEY)->delete();
    }
}
