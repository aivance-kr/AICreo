<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 블로그형 테마용 광고 위치 추가 — 홈 목록 중간(인피드)과 사이드바.
 */
class AddFeedAndSidebarAdPositions extends Migration
{
    public function up(): void
    {
        $this->db->query(
            'ALTER TABLE ads MODIFY position '
            . "ENUM('home_top','home_feed','home_bottom','post_top','post_bottom','sidebar') NOT NULL DEFAULT 'home_top'",
        );
    }

    public function down(): void
    {
        // 새 위치로 등록된 광고는 되돌릴 때 home_top 으로 옮긴다(ENUM 축소 시 데이터 손실 방지).
        $this->db->query("UPDATE ads SET position = 'home_top' WHERE position IN ('home_feed','sidebar')");
        $this->db->query(
            'ALTER TABLE ads MODIFY position '
            . "ENUM('home_top','home_bottom','post_top','post_bottom') NOT NULL DEFAULT 'home_top'",
        );
    }
}
