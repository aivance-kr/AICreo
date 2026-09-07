<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBlogHomePostLimitSetting extends Migration
{
    public function up(): void
    {
        $exists = $this->db->table('settings')
            ->where('key', 'blog_home_post_limit')
            ->get()
            ->getRow();

        if ($exists) {
            return;
        }

        $this->db->table('settings')->insert([
            'group'      => 'general',
            'key'        => 'blog_home_post_limit',
            'value'      => '10',
            'label'      => '블로그 메인 최신 글 수',
            'type'       => 'number',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function down(): void
    {
        $this->db->table('settings')->where('key', 'blog_home_post_limit')->delete();
    }
}
