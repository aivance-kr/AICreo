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

    public function testConvertsMarkdownHeadingParagraphs(): void
    {
        $out = post_content_html('<p>## 설치 정보</p><p>본문</p><p>### **굵은** 제목</p><p>#해시태그</p><p>####### 일곱</p>');

        $this->assertStringContainsString('<h2>설치 정보</h2><p>본문</p>', $out);
        $this->assertStringContainsString('<h3><strong>굵은</strong> 제목</h3>', $out);
        $this->assertStringContainsString('<p>#해시태그</p>', $out);
        $this->assertStringContainsString('<p>####### 일곱</p>', $out);
    }

    public function testKeepsMarkdownHeadingInCode(): void
    {
        $out = post_content_html('<pre class="language-bash"># 주석\n## 두번째</pre>');

        $this->assertStringNotContainsString('<h', $out);
    }

    public function testConvertsMarkdownInlineCodeItalicAndLinks(): void
    {
        $out = post_content_html('<p>`a **b**` 와 *기울임* 과 [링크](https://example.com/x?a=1&b=2) 와 [내부](/board/1)</p>');

        $this->assertStringContainsString('<code>a **b**</code>', $out);
        $this->assertStringContainsString('<em>기울임</em>', $out);
        $this->assertStringContainsString('<a href="https://example.com/x?a=1&amp;b=2">링크</a>', $out);
        $this->assertStringContainsString('<a href="/board/1">내부</a>', $out);
    }

    public function testRejectsUnsafeMarkdownLinkAndKeepsPlainAsterisks(): void
    {
        $out = post_content_html('<p>[x](javascript:alert(1)) 2 * 3 * 4 <a href="/a">[y](/z)</a></p>');

        $this->assertStringNotContainsString('<a href="javascript', $out);
        $this->assertStringContainsString('[x](javascript:alert(1))', $out);
        $this->assertStringContainsString('2 * 3 * 4', $out);
        $this->assertStringContainsString('<a href="/a">[y](/z)</a>', $out);
    }

    public function testConvertsMarkdownLists(): void
    {
        $out = post_content_html('<p>- 하나</p><p>- **둘**</p><p>본문</p><p>1. 가<br>2. 나</p><p>-하이픈</p>');

        $this->assertStringContainsString('<ul><li>하나</li><li><strong>둘</strong></li></ul><p>본문</p>', $out);
        $this->assertStringContainsString('<ol><li>가</li><li>나</li></ol>', $out);
        $this->assertStringContainsString('<p>-하이픈</p>', $out);
    }

    public function testConvertsMarkdownQuoteAndRule(): void
    {
        $out = post_content_html('<p>&gt; 인용문<br>&gt; 둘째 줄</p><p>---</p><p>a --- b</p>');

        $this->assertStringContainsString('<blockquote><p>인용문<br />둘째 줄</p></blockquote>', $out);
        $this->assertStringContainsString('<hr />', $out);
        $this->assertStringContainsString('<p>a --- b</p>', $out);
    }

    public function testKeepsMarkdownBlocksInCode(): void
    {
        $out = post_content_html('<pre class="language-bash">- a\n&gt; b\n---\n`c`</pre>');

        $this->assertStringNotContainsString('<ul', $out);
        $this->assertStringNotContainsString('<blockquote', $out);
        $this->assertStringNotContainsString('<hr', $out);
        $this->assertStringNotContainsString('<code>', $out);
    }
}
