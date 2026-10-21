<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\HtmlSanitizer;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
final class HtmlSanitizerTest extends CIUnitTestCase
{
    #[DataProvider('provideRemovesDangerousMarkup')]
    public function testRemovesDangerousMarkup(string $input, string $forbidden): void
    {
        $this->assertStringNotContainsStringIgnoringCase($forbidden, HtmlSanitizer::clean($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRemovesDangerousMarkup(): iterable
    {
        yield 'script 대소문자 혼합' => ['<ScRiPt>alert(1)</sCrIpT>x', 'alert'];

        yield 'on 이벤트 슬래시 구분' => ['<img/src=x/onerror=alert(1)>', 'onerror'];

        yield 'svg onload' => ['<svg onload=alert(1)>', 'svg'];

        yield 'javascript 링크(개행 삽입)' => ["<a href=\"java\nscript:alert(1)\">x</a>", 'script:'];

        yield 'javascript 링크(엔티티)' => ['<a href="&#106;avascript:alert(1)">x</a>', 'avascript'];

        yield 'data URI 링크' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', 'data:'];

        yield 'iframe' => ['<iframe src="https://evil.example"></iframe>', 'iframe'];

        yield 'form' => ['<form action="https://evil.example"><input></form>', 'form'];

        yield 'style 속성 expression' => ['<p style="background:url(javascript:alert(1))">x</p>', 'javascript'];

        yield 'math/mutation' => ['<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>', 'onerror'];

        yield 'Prism data-dependencies' => ['<pre class="language-php" data-dependencies="../../evil"><code>x</code></pre>', 'data-dependencies'];
    }

    public function testKeepsEditorMarkup(): void
    {
        $html = '<h2>제목</h2><p style="text-align: center;"><strong>굵게</strong> <em>기울임</em> <a href="https://example.com" target="_blank">링크</a></p>'
            . '<ul><li>하나</li></ul><table><tbody><tr><td>셀</td></tr></tbody></table><img src="/uploads/a.png" alt="그림">'
            . '<pre class="language-php"><code>echo &lt;1&gt;;</code></pre>';
        $out = HtmlSanitizer::clean($html);

        foreach (['<h2>제목</h2>', '<strong>굵게</strong>', 'text-align:center', 'href="https://example.com"', '<li>하나</li>', '<td>셀</td>', 'src="/uploads/a.png"', 'alt="그림"', '<pre class="language-php">', 'echo &lt;1&gt;;'] as $needle) {
            $this->assertStringContainsString($needle, $out);
        }
        $this->assertStringContainsString('noopener', $out);
    }

    public function testDropsUnknownClassesAndEmptyInput(): void
    {
        $this->assertStringNotContainsString('class=', HtmlSanitizer::clean('<p class="evil">x</p>'));
        $this->assertSame('', HtmlSanitizer::clean(null));
        $this->assertSame('', HtmlSanitizer::clean(''));
    }
}
