<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\SeoHelper;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SeoHelperTest extends CIUnitTestCase
{
    private function helper(array $settings = []): SeoHelper
    {
        return new SeoHelper($settings + [
            'site_name' => '테스트 사이트',
            'site_desc' => '사이트 설명',
        ]);
    }

    public function testRenderUsesSiteDefaultsWithoutPage(): void
    {
        $html = $this->helper()->render();

        $this->assertStringContainsString('<title>테스트 사이트</title>', $html);
        $this->assertStringContainsString('content="사이트 설명"', $html);
        $this->assertStringContainsString('property="og:type" content="website"', $html);
    }

    public function testRenderPrefersPageMeta(): void
    {
        $html = $this->helper()->render([
            'meta_title' => '페이지 제목',
            'meta_desc'  => '페이지 설명',
            'title'      => '무시됨',
        ]);

        $this->assertStringContainsString('<title>페이지 제목</title>', $html);
        $this->assertStringContainsString('content="페이지 설명"', $html);
    }

    public function testRenderFallsBackToPageTitleWhenNoMetaTitle(): void
    {
        $html = $this->helper()->render(['title' => '글 제목']);

        $this->assertStringContainsString('<title>글 제목</title>', $html);
    }

    public function testRenderIncludesOgImageWhenPresent(): void
    {
        $html = $this->helper(['site_logo' => 'uploads/logo.png'])->render();

        $this->assertStringContainsString('property="og:image"', $html);
        $this->assertStringContainsString('uploads/logo.png', $html);
    }

    public function testRenderOmitsOgImageWhenAbsent(): void
    {
        $html = $this->helper()->render();

        $this->assertStringNotContainsString('og:image', $html);
    }

    public function testRenderEscapesOutput(): void
    {
        $html = $this->helper(['site_name' => '<script>alert(1)</script>'])->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderIncludesNaverVerification(): void
    {
        $html = $this->helper(['naver_verify' => 'naver-token'])->render();

        $this->assertStringContainsString('name="naver-site-verification" content="naver-token"', $html);
    }

    public function testRenderIncludesGoogleAndBingVerification(): void
    {
        $html = $this->helper([
            'google_verify' => 'google-token',
            'bing_verify'   => 'bing-token',
        ])->render();

        $this->assertStringContainsString('name="google-site-verification" content="google-token"', $html);
        $this->assertStringContainsString('name="msvalidate.01" content="bing-token"', $html);
    }

    public function testRenderIncludesDaumVerification(): void
    {
        $html = $this->helper(['daum_verify' => 'daum-token'])->render();

        $this->assertStringContainsString('name="DaumVerification" content="daum-token"', $html);
    }

    public function testRenderOutputsCanonicalLink(): void
    {
        $html = $this->helper()->render();

        $this->assertStringContainsString('<link rel="canonical" href="', $html);
    }

    public function testRenderPrefersExplicitCanonical(): void
    {
        $html = $this->helper()->render(['canonical' => 'https://example.com/foo']);

        $this->assertStringContainsString('<link rel="canonical" href="https://example.com/foo">', $html);
        $this->assertStringContainsString('property="og:url" content="https://example.com/foo"', $html);
    }

    public function testRenderDefaultsToIndexFollow(): void
    {
        $html = $this->helper()->render();

        $this->assertStringContainsString('name="robots" content="index, follow"', $html);
    }

    public function testRenderRespectsNoindexFlag(): void
    {
        $html = $this->helper()->render(['noindex' => true]);

        $this->assertStringContainsString('name="robots" content="noindex, nofollow"', $html);
    }

    public function testRenderRespectsExplicitRobots(): void
    {
        $html = $this->helper()->render(['robots' => 'noindex, follow']);

        $this->assertStringContainsString('name="robots" content="noindex, follow"', $html);
    }

    public function testRenderIncludesTwitterCardAndLocale(): void
    {
        $html = $this->helper()->render(['title' => '글 제목']);

        $this->assertStringContainsString('name="twitter:card" content="summary_large_image"', $html);
        $this->assertStringContainsString('name="twitter:title" content="글 제목"', $html);
        $this->assertStringContainsString('property="og:locale" content="ko_KR"', $html);
    }

    public function testRenderFallsBackToOgDefaultImage(): void
    {
        $html = $this->helper(['og_default_image' => 'uploads/default-card.png'])->render();

        $this->assertStringContainsString('property="og:image"', $html);
        $this->assertStringContainsString('uploads/default-card.png', $html);
        $this->assertStringContainsString('name="twitter:image"', $html);
    }

    public function testRenderPageOgImageOverridesDefault(): void
    {
        $html = $this->helper(['og_default_image' => 'uploads/default-card.png'])
            ->render(['og_image' => 'uploads/page-card.png']);

        $this->assertStringContainsString('uploads/page-card.png', $html);
        $this->assertStringNotContainsString('default-card.png', $html);
    }

    public function testRenderOutputsImageDimensionsWhenKnown(): void
    {
        $html = $this->helper([
            'site_logo'       => 'uploads/logo.png',
            'og_image_width'  => 1200,
            'og_image_height' => 630,
        ])->render();

        $this->assertStringContainsString('property="og:image:width" content="1200"', $html);
        $this->assertStringContainsString('property="og:image:height" content="630"', $html);
    }

    public function testRenderOmitsImageDimensionsWhenUnknown(): void
    {
        $html = $this->helper(['site_logo' => 'uploads/logo.png'])->render();

        $this->assertStringNotContainsString('og:image:width', $html);
    }

    public function testGaScriptEmptyWithoutId(): void
    {
        $this->assertSame('', $this->helper()->gaScript());
    }

    public function testGaScriptRendersWithId(): void
    {
        $html = $this->helper(['ga_id' => 'G-ABC123'])->gaScript();

        $this->assertStringContainsString('G-ABC123', $html);
        $this->assertStringContainsString('gtag', $html);
    }
}
