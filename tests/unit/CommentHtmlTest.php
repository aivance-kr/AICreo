<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CommentHtmlTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('post');
    }

    public function testLinksUrl(): void
    {
        $html = comment_html('보세요 https://example.com/a?b=1&c=2 끝');

        $this->assertStringContainsString('<a href="https://example.com/a?b=1&amp;c=2"', $html);
        $this->assertStringContainsString('rel="nofollow ugc noopener noreferrer"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringEndsWith('</a> 끝', $html);
    }

    public function testTrailingPunctuationExcludedFromLink(): void
    {
        $html = comment_html('(https://example.com/x), 그리고 http://a.kr.');

        $this->assertStringContainsString('href="https://example.com/x"', $html);
        $this->assertStringContainsString('href="http://a.kr"', $html);
        $this->assertStringContainsString('</a>),', $html);
    }

    public function testEscapesHtmlAndKeepsNewlines(): void
    {
        $html = comment_html("<script>alert(1)</script>\n두번째 줄");

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<br', $html);
    }

    public function testQuoteCannotBreakOutOfHref(): void
    {
        $html = comment_html('https://x.com/"onmouseover="alert(1)');

        $this->assertStringNotContainsString('"onmouseover', $html);
        $this->assertStringContainsString('href="https://x.com/"', $html);
    }

    public function testNonHttpSchemesNotLinked(): void
    {
        $this->assertStringNotContainsString('<a ', comment_html('javascript:alert(1) ftp://x.com'));
    }
}
