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
        $out  = post_content_html($html);

        $this->assertStringContainsString('src="/uploads/board/images/a.png"', $out);
        $this->assertStringContainsString('src="/uploads/b.png"', $out);
        $this->assertStringContainsString('src="/uploads/c.png"', $out);
        $this->assertStringNotContainsString('index.php', $out);
        $this->assertStringNotContainsString('../', $out);
    }

    public function testRewritesUnquotedSrcAndHref(): void
    {
        $out = post_content_html('<img src=../uploads/a.jpg><a href="index.php/uploads/f.pdf">f</a>');

        $this->assertStringContainsString('src="/uploads/a.jpg"', $out);
        $this->assertStringContainsString('href="/uploads/f.pdf"', $out);
    }

    public function testLeavesOtherUrlsAndTextUntouched(): void
    {
        $out = post_content_html('<img src="https://cdn.example.com/uploads/a.png"><p>uploads/ 폴더</p><a href="/board/uploads/x">x</a>');

        $this->assertStringContainsString('src="https://cdn.example.com/uploads/a.png"', $out);
        $this->assertStringContainsString('<p>uploads/ 폴더</p>', $out);
        $this->assertStringContainsString('href="/board/uploads/x"', $out);
        $this->assertSame('', post_content_html(null));
    }

    public function testStripsDangerousMarkupOnOutput(): void
    {
        $out = post_content_html('<p onclick="x()">a</p><img src=x onerror=alert(1)><a href="javascript:alert(1)">l</a><script>alert(1)</script>');

        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('onerror', $out);
        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringNotContainsString('<script', $out);
    }

    public function testConvertsMarkdownBoldInTextNodes(): void
    {
        $out = post_content_html('<p>**90분짜리 「AI 여행강의」 PPT(17장)**</p><p>a **b** c **d**</p>');

        $this->assertStringContainsString('<p><strong>90분짜리 「AI 여행강의」 PPT(17장)</strong></p>', $out);
        $this->assertStringContainsString('<p>a <strong>b</strong> c <strong>d</strong></p>', $out);
    }

    public function testKeepsMarkdownBoldInCodeAndAttributes(): void
    {
        $out = post_content_html('<pre class="language-php">$a **b** c</pre><p><code>**x**</code> <a href="/a" title="**t**">l</a></p><p>2 ** 3 ** 4 **</p>');

        $this->assertStringContainsString('$a **b** c', $out);
        $this->assertStringContainsString('<code>**x**</code>', $out);
        $this->assertStringContainsString('title="**t**"', $out);
        $this->assertStringNotContainsString('<strong>', $out);
    }
}
