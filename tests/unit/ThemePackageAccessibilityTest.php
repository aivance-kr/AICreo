<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ZipArchive;

/**
 * 샘플 테마 패키지 접근성 계약 테스트.
 *
 * 테마가 layouts/main.php 를 통째로 교체하면 건너뛰기 링크·본문 랜드마크·
 * 전역 토큰이 함께 사라진다. 저장소 안의 뷰는 ViewAccessibilityTest 가
 * 지키지만 ZIP 내부는 그 검사가 닿지 않아 사각지대였다.
 *
 * 여기서 지키는 것은 «우리가 배포하는» 샘플 테마다. 제3자 테마까지 강제하려면
 * 업로드 검증(ThemeService)에 같은 규칙을 넣어야 하는데, 그건 기존 테마를
 * 거부하게 되는 제품 결정이라 별도로 다룬다.
 *
 * @internal
 */
final class ThemePackageAccessibilityTest extends CIUnitTestCase
{
    /**
     * 저장소가 배포하는 샘플 테마 ZIP
     */
    private const SAMPLE_THEMES = ['dark', 'violet', 'spring'];

    /**
     * ZIP 안의 레이아웃 파일을 꺼내온다.
     */
    private function layoutOf(string $theme): string
    {
        $path = ROOTPATH . $theme . '.zip';
        $this->assertFileExists($path, "샘플 테마 {$theme}.zip 이 없습니다.");

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, "{$theme}.zip 을 열 수 없습니다.");

        $source = $zip->getFromName('views/layouts/main.php');
        $zip->close();

        $this->assertIsString(
            $source,
            "{$theme}.zip 에 views/layouts/main.php 가 없습니다(테마 필수 파일).",
        );

        return $source;
    }

    /**
     * 레이아웃을 교체하는 테마도 건너뛰기 링크와 본문 랜드마크를 유지해야 한다.
     * (WCAG 2.4.1 / 1.3.1 · KWCAG 「반복 영역 건너뛰기」)
     */
    #[DataProvider('themeProvider')]
    public function testSampleThemeKeepsSkipLinkAndLandmark(string $theme): void
    {
        $layout = $this->layoutOf($theme);

        $this->assertStringContainsString(
            'class="skip-link"',
            $layout,
            "{$theme} 테마에 건너뛰기 링크가 없습니다.",
        );
        $this->assertStringContainsString(
            'id="main-content"',
            $layout,
            "{$theme} 테마에 본문 랜드마크(id=\"main-content\")가 없습니다.",
        );
    }

    /**
     * 전역 토큰을 테마 CSS 보다 먼저 실어야 색·초점 표시·건너뛰기 링크 스타일이 산다.
     */
    #[DataProvider('themeProvider')]
    public function testSampleThemeLoadsGlobalTokensBeforeThemeCss(string $theme): void
    {
        $layout = $this->layoutOf($theme);

        $tokensAt = strpos($layout, '/css/tokens.css');
        $themeAt  = strpos($layout, "/themes/{$theme}/css/style.css");

        $this->assertNotFalse($tokensAt, "{$theme} 테마가 /css/tokens.css 를 싣지 않습니다.");
        $this->assertNotFalse($themeAt, "{$theme} 테마가 자신의 style.css 를 싣지 않습니다.");
        $this->assertLessThan(
            $themeAt,
            $tokensAt,
            "{$theme} 테마는 tokens.css 를 자신의 CSS 보다 먼저 실어야 변수 덮어쓰기가 동작합니다.",
        );
    }

    /**
     * 외부 CDN 자원은 무결성 검증을 달아야 한다.
     * 재판매되는 템플릿이라 CDN 침해가 모든 클라이언트 사이트로 번진다.
     */
    #[DataProvider('themeProvider')]
    public function testSampleThemePinsCdnIntegrity(string $theme): void
    {
        $layout = $this->layoutOf($theme);

        preg_match_all('/<(?:link|script)\b[^>]*cdn\.jsdelivr\.net[^>]*>/s', $layout, $matches);
        $this->assertNotEmpty($matches[0], "{$theme} 테마에서 CDN 참조를 찾지 못했습니다.");

        foreach ($matches[0] as $tag) {
            $this->assertStringContainsString(
                'integrity="sha384-',
                $tag,
                "{$theme} 테마의 CDN 자원에 무결성 해시가 없습니다: " . trim((string) preg_replace('/\s+/', ' ', $tag)),
            );
        }
    }

    /**
     * 배너 이미지에 빈 alt 를 남기면 링크된 배너가 이름 없는 링크가 된다.
     */
    #[DataProvider('themeProvider')]
    public function testSampleThemeHasNoEmptyBannerAlt(string $theme): void
    {
        $layout = $this->layoutOf($theme);

        $this->assertStringNotContainsString(
            'alt=""',
            $layout,
            "{$theme} 테마에 alt 가 빈 이미지가 있습니다. alt_text 를 쓰도록 고치세요.",
        );
    }

    /**
     * @return list<array{string}>
     */
    public static function themeProvider(): iterable
    {
        return array_map(static fn (string $t): array => [$t], self::SAMPLE_THEMES);
    }
}
