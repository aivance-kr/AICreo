<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSignupEnabledSetting extends Migration
{
    public function up(): void
    {
        $exists = $this->db->table('settings')
            ->where('key', 'signup_enabled')
            ->get()
            ->getRow();

        if ($exists) {
            return;
        }

        $this->db->table('settings')->insert([
            'group'      => 'general',
            'key'        => 'signup_enabled',
            'value'      => '0',
            'label'      => '회원가입 허용',
            'type'       => 'boolean',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function down(): void
    {
        $this->db->table('settings')->where('key', 'signup_enabled')->delete();
    }
}
