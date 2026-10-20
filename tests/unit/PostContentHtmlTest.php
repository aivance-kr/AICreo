<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PostContentHtmlTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('post');
    }

    public function testRewritesRelativeUploadPathsToRoot(): void
    {
        $html = '<img src="../../uploads/board/images/a.png"><img src="uploads/b.png"><img src="/index.php/uploads/c.png">';

        $this->assertSame(
            '<img src="/uploads/board/images/a.png"><img src="/uploads/b.png"><img src="/uploads/c.png">',
            post_content_html($html),
        );
    }

    public function testRewritesUnquotedSrcAndHref(): void
    {
        $this->assertSame(
            '<img src=/uploads/a.jpg><a href="/uploads/f.pdf">f</a>',
            post_content_html('<img src=../uploads/a.jpg><a href="index.php/uploads/f.pdf">f</a>'),
        );
    }

    public function testLeavesOtherUrlsAndTextUntouched(): void
    {
        $html = '<img src="https://cdn.example.com/uploads/a.png"><p>uploads/ 폴더</p><a href="/board/uploads/x">x</a>';

        $this->assertSame($html, post_content_html($html));
        $this->assertSame('', post_content_html(null));
    }
}
