<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BoardModel;
use App\Models\PostModel;
use App\Models\SettingModel;
use Tests\Support\EnablesLocales;
use Tests\Support\FeatureTestCase;

/**
 * 언어 전환 UI와 화면 고정 문구 (#566 5단계).
 *
 * @internal
 */
final class LangSwitcherTest extends FeatureTestCase
{
    use EnablesLocales;

    protected function tearDown(): void
    {
        if (isset($this->originalRoutes)) {
            $this->restoreLocales();
        }

        parent::tearDown();
    }

    private function makePost(string $locale, ?int $group = null): int
    {
        $posts = new PostModel();
        $id    = (int) $posts->insert([
            'board_id' => (int) (new BoardModel())->getBySlug('notice')['id'],
            'title'    => 'T',
            'content'  => 'c',
            'locale'   => $locale,
        ]);
        $posts->update($id, ['translation_group_id' => $group ?? $id]);

        return $id;
    }

    public function testSwitcherHiddenWhenSingleLanguage(): void
    {
        $this->assertStringNotContainsString('lang-switcher', $this->get('/')->getBody());
    }

    public function testSwitcherLinksToEachLanguageHome(): void
    {
        $this->enableLocales();
        $body = $this->get('/')->getBody();

        $this->assertStringContainsString('class="lang-switcher"', $body);
        $this->assertStringContainsString('hreflang="en" lang="en" href="' . base_url('en') . '"', $body);
        $this->assertStringContainsString('aria-current="true">KO</a>', $body);
    }

    public function testSwitcherUsesTranslationOfPost(): void
    {
        $this->enableLocales();
        $ko = $this->makePost('ko');
        $en = $this->makePost('en', $ko);

        $body = $this->get('board/notice/' . $ko)->getBody();

        $this->assertStringContainsString('href="' . base_url('en/board/notice/' . $en) . '"', $body);
    }

    public function testSwitcherFallsBackToHomeWhenPostUntranslated(): void
    {
        $this->enableLocales();
        $ko = $this->makePost('ko');

        $body = $this->get('board/notice/' . $ko)->getBody();

        $this->assertStringContainsString('hreflang="en" lang="en" href="' . base_url('en') . '"', $body);
    }

    public function testSwitcherKeepsPathAndQueryOnNeutralPages(): void
    {
        $this->enableLocales();
        $body = $this->get('en/search?q=abc')->getBody();

        $this->assertStringContainsString('hreflang="ko" lang="ko" href="' . base_url('search') . '?q=abc"', $body);
    }

    public function testSettingCanHideSwitcher(): void
    {
        $this->enableLocales();
        (new SettingModel())->saveSettings(['show_lang_switcher' => '0']);

        $this->assertStringNotContainsString('lang-switcher', $this->get('/')->getBody());
    }

    public function testNavbarStringsFollowLocale(): void
    {
        $this->enableLocales();

        $this->assertStringContainsString('로그인', $this->get('/')->response()->getBody());
        $en = $this->get('en')->response()->getBody();
        $this->assertStringContainsString('Log in', $en);
        $this->assertStringNotContainsString('>로그인</a>', $en);
    }
}
