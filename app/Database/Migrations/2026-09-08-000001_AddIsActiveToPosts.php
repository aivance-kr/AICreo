<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 관리자 "전체 게시물" 숨기기/노출 토글 지원 — 게시글에 is_active 컬럼 추가.
 * 비활성(0) 글은 공개 목록·검색·상세·사이트맵·블로그 미리보기에서 제외된다.
 */
class AddIsActiveToPosts extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('posts', [
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'is_secret'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('posts', 'is_active');
    }
}
