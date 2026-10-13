<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\ThemeView;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * 블로그형 테마가 쓰는 게시글 요약 헬퍼와 테마 판별.
 *
 * @internal
 */
final class PostHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('post');
    }

    public function testExcerptStripsTagsAndCollapsesWhitespace(): void
    {
        $this->assertSame('안녕 하세요 &', post_excerpt("<p>안녕\n  <b>하세요</b></p> &amp;", 50));
    }

    public function testExcerptTruncatesByCharacterCount(): void
    {
        $this->assertSame('가나다…', post_excerpt('가나다라마바사', 3));
    }

    public function testReadMinutesIsAtLeastOne(): void
    {
        $this->assertSame(1, post_read_minutes('<p>짧은 글</p>'));
        $this->assertSame(3, post_read_minutes(str_repeat('가', 1001)));
    }

    public function testBlogThemeFamily(): void
    {
        foreach (['blog', 'ilgan', 'cobalt', 'quiet', 'devnotes'] as $theme) {
            $this->assertTrue(ThemeView::isBlogTheme($theme), $theme);
        }

        foreach (['default', 'dark', 'spring', ''] as $theme) {
            $this->assertFalse(ThemeView::isBlogTheme($theme), $theme);
        }
    }
}
