<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PostModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PostThumbnailTest extends CIUnitTestCase
{
    public function testExtractsFirstImageSrc(): void
    {
        $content = '<p>본문</p><img src="/uploads/board/images/2026/09/a.jpg" alt=""><img src="/uploads/board/images/2026/09/b.jpg">';

        $this->assertSame('/uploads/board/images/2026/09/a.jpg', PostModel::extractThumbnail($content));
    }

    public function testExtractsUnquotedSrcAttribute(): void
    {
        // 워드프레스 이관 게시글 일부는 src 속성에 따옴표가 없다 — 상세 페이지는 그대로
        // 렌더링돼 문제없지만, 목록 썸네일 추출 정규식은 따옴표를 요구해 놓쳤었다.
        $content = "\n<img src=/uploads/imported/2006/07/1046279519.jpg height=\"600\" width=\"800\" alt=\"\"/><br>";

        $this->assertSame('/uploads/imported/2006/07/1046279519.jpg', PostModel::extractThumbnail($content));
    }

    public function testReturnsNullWhenNoImage(): void
    {
        $this->assertNull(PostModel::extractThumbnail('<p>이미지 없는 본문</p>'));
    }

    public function testReturnsNullForEmptyContent(): void
    {
        $this->assertNull(PostModel::extractThumbnail(null));
        $this->assertNull(PostModel::extractThumbnail(''));
    }
}
