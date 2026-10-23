<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\OgImageGenerator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class OgImageGeneratorTest extends CIUnitTestCase
{
    public function testFindFontReturnsNullForMissingConfiguredFontWithoutSystemFallback(): void
    {
        $this->assertNull(OgImageGenerator::findFont('/nonexistent/font.ttf', []));
    }

    public function testFindFontPrefersConfiguredPath(): void
    {
        $font = OgImageGenerator::findFont(null);
        if ($font === null) {
            $this->markTestSkipped('시스템에 한글 폰트가 없다.');
        }

        $this->assertSame($font, OgImageGenerator::findFont($font, []));
    }

    public function testRenderReturnsPngOfOgSize(): void
    {
        $font = OgImageGenerator::findFont(null);
        if ($font === null) {
            $this->markTestSkipped('시스템에 한글 폰트가 없다.');
        }

        $png = (new OgImageGenerator($font))->render('아주 긴 제목 ' . str_repeat('테스트 ', 30), '내 회사');

        $info = getimagesizefromstring($png);
        $this->assertSame([1200, 630], [$info[0], $info[1]]);
        $this->assertSame(IMAGETYPE_PNG, $info[2]);
    }
}
