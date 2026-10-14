<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\SettingModel;
use Tests\Support\AdminTestCase;

/**
 * SEO 관리자 설정 화면 (#202).
 *
 * @internal
 */
final class SeoSettingTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        cache()->delete('site_settings');
        cache()->delete('seo_robots');
    }

    public function testSeoTabRendersNewFields(): void
    {
        $body = $this->withSession($this->adminSession)->get('admin/settings/seo')->getBody();

        // (테스트 하네스가 한글을 HTML 엔티티로 변환하므로 ASCII 속성으로 검증)
        $this->assertStringContainsString('name="og_default_image"', $body);
        $this->assertStringContainsString('name="google_verify"', $body);
        $this->assertStringContainsString('name="bing_verify"', $body);
        $this->assertStringContainsString('name="daum_verify"', $body);
        // org_type 은 select, ai_crawlers_allow 는 switch(checkbox)
        $this->assertStringContainsString('<select name="org_type"', $body);
        $this->assertStringContainsString('type="checkbox" class="form-check-input" role="switch"', $body);
        $this->assertStringContainsString('name="ai_crawlers_allow"', $body);
    }

    public function testSaveSeoSettingsPersists(): void
    {
        $result = $this->withSession($this->adminSession)->post('admin/settings/seo', [
            'og_default_image'  => 'uploads/media/card.png',
            'google_verify'     => 'google-token',
            'bing_verify'       => 'bing-token',
            'daum_verify'       => 'daum-token',
            'org_type'          => 'LocalBusiness',
            'ai_crawlers_allow' => '1',
        ]);

        $result->assertRedirectTo('/admin/settings/seo');

        $map = (new SettingModel())->getAllAsMap();
        $this->assertSame('uploads/media/card.png', $map['og_default_image']);
        $this->assertSame('google-token', $map['google_verify']);
        $this->assertSame('bing-token', $map['bing_verify']);
        $this->assertSame('daum-token', $map['daum_verify']);
        $this->assertSame('LocalBusiness', $map['org_type']);
        $this->assertSame('1', $map['ai_crawlers_allow']);
    }

    public function testSavedVerifyCodesAppearInSeoHelperOutput(): void
    {
        $this->withSession($this->adminSession)->post('admin/settings/seo', [
            'google_verify' => 'g-code-123',
            'bing_verify'   => 'b-code-456',
            'daum_verify'   => 'd-code-789',
        ]);
        cache()->delete('site_settings');

        // 홈 페이지 <head> 에 즉시 반영
        $body = $this->get('/')->getBody();
        $this->assertStringContainsString('name="google-site-verification" content="g-code-123"', $body);
        $this->assertStringContainsString('name="msvalidate.01" content="b-code-456"', $body);
        $this->assertStringContainsString('name="DaumVerification" content="d-code-789"', $body);
    }

    public function testDisablingAiCrawlersBlocksThemInRobots(): void
    {
        // 스위치 OFF: 체크박스 미포함 → hidden value=0 만 전송되는 상황 재현
        $this->withSession($this->adminSession)->post('admin/settings/seo', [
            'ai_crawlers_allow' => '0',
        ]);
        cache()->delete('seo_robots');

        $body    = $this->get('robots.txt')->getBody();
        $section = strstr($body, 'User-agent: GPTBot') ?: '';
        $this->assertStringContainsString('Disallow: /', $section);
    }
}
