<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BoardModel;
use App\Models\MenuModel;
use App\Models\PageModel;
use App\Models\PostModel;
use CodeIgniter\Config\Factories;
use Config\I18n;
use Tests\Support\AdminTestCase;

/**
 * 관리자 번역 흐름 (#566 4단계).
 *
 * @internal
 */
final class TranslationAdminTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $config          = new I18n();
        $config->enabled = 'ko,en';
        Factories::injectMock('config', 'I18n', $config);
    }

    protected function tearDown(): void
    {
        Factories::reset('config');
        parent::tearDown();
    }

    private function makePost(string $locale = 'ko'): int
    {
        return (int) (new PostModel())->insert([
            'board_id' => (int) (new BoardModel())->getBySlug('notice')['id'],
            'title'    => 'T-' . $locale,
            'content'  => 'c',
            'locale'   => $locale,
        ]);
    }

    public function testTranslatePageCreatesDraftAndRedirectsToEdit(): void
    {
        $pages = new PageModel();
        $src   = (int) $pages->insert(['slug' => 'adm-tr', 'title' => '소개', 'status' => 'published']);

        $result = $this->withSession($this->adminSession)->post("admin/pages/{$src}/translate/en");

        $en = $pages->where('locale', 'en')->where('slug', 'adm-tr')->first();
        $this->assertNotNull($en);
        $result->assertRedirectTo('/admin/pages/' . $en['id'] . '/edit');
    }

    public function testTranslatePostCreatesHiddenCopyAndRedirectsToBoardEdit(): void
    {
        $src    = $this->makePost();
        $result = $this->withSession($this->adminSession)->post("admin/posts/{$src}/translate/en");

        $en = (new PostModel())->where('locale', 'en')->where('translation_group_id', $src)->first();
        $this->assertNotNull($en);
        $this->assertSame('0', (string) $en['is_active']);
        $result->assertRedirectTo('/board/notice/' . $en['id'] . '/edit');
    }

    public function testTranslateWithUnavailableLocaleCreatesNothing(): void
    {
        $src = $this->makePost();
        $this->withSession($this->adminSession)->post("admin/posts/{$src}/translate/fr");

        $this->assertSame(1, (new PostModel())->where('translation_group_id', $src)->orWhere('id', $src)->countAllResults());
    }

    public function testTranslateRequiresAdmin(): void
    {
        $src = $this->makePost();
        $this->withSession($this->memberSession)->post("admin/posts/{$src}/translate/en");

        $this->assertNull((new PostModel())->where('locale', 'en')->first());
    }

    public function testSameSlugAllowedInOtherLocaleButNotWithinOne(): void
    {
        $pages = new PageModel();
        $pages->insert(['slug' => 'dup-slug', 'title' => '한글', 'status' => 'published']);

        $this->withSession($this->adminSession)->post('admin/pages/create', ['slug' => 'dup-slug', 'title' => 'English', 'locale' => 'en']);
        $this->assertSame(1, $pages->where('slug', 'dup-slug')->where('locale', 'en')->countAllResults());

        $this->withSession($this->adminSession)->post('admin/pages/create', ['slug' => 'dup-slug', 'title' => 'Again', 'locale' => 'en']);
        $this->assertSame(1, $pages->where('slug', 'dup-slug')->where('locale', 'en')->countAllResults());
    }

    public function testPageListFiltersByLocale(): void
    {
        $pages = new PageModel();
        $pages->insert(['slug' => 'only-ko', 'title' => 'KO_ONLY_TITLE', 'status' => 'published']);
        $pages->insert(['slug' => 'only-en', 'title' => 'EN_ONLY_TITLE', 'status' => 'published', 'locale' => 'en']);

        $body = $this->withSession($this->adminSession)->get('admin/pages?locale=en')->getBody();

        $this->assertStringContainsString('EN_ONLY_TITLE', $body);
        $this->assertStringNotContainsString('KO_ONLY_TITLE', $body);
    }

    public function testPostListFiltersByLocaleAndShowsBadge(): void
    {
        $posts = new PostModel();
        $posts->update($this->makePost('ko'), ['title' => 'KO_POST_MARK']);
        $posts->update($this->makePost('en'), ['title' => 'EN_POST_MARK']);

        $body = $this->withSession($this->adminSession)->get('admin/posts?locale=en')->getBody();

        $this->assertStringContainsString('EN_POST_MARK', $body);
        $this->assertStringNotContainsString('KO_POST_MARK', $body);
    }

    public function testMenusAreManagedPerLocale(): void
    {
        $this->withSession($this->adminSession)->post('admin/menus', ['title' => 'EN_MENU_MARK', 'url' => '/en/about', 'locale' => 'en']);

        $this->assertSame('en', (new MenuModel())->where('title', 'EN_MENU_MARK')->first()['locale']);
        $this->assertStringContainsString('EN_MENU_MARK', $this->withSession($this->adminSession)->get('admin/menus?locale=en')->getBody());
        $this->assertStringNotContainsString('EN_MENU_MARK', $this->withSession($this->adminSession)->get('admin/menus')->getBody());
    }
}
