<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 배너 대체 텍스트 컬럼 추가.
 *
 * 링크가 걸린 배너는 <a> 안의 유일한 콘텐츠가 이미지라, alt 가 비어 있으면
 * 접근 가능한 이름이 없는 링크가 된다(WCAG 2.4.4 / 4.1.2, KWCAG 대체 텍스트).
 * 운영자가 배너 등록 화면에서 직접 입력하도록 컬럼을 연다.
 */
class AddAltTextToBanners extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('banners', [
            'alt_text' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'image_path',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('banners', 'alt_text');
    }
}
