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
