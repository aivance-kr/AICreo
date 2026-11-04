<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BoardModel;
use App\Models\PageModel;
use App\Models\PostModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\EnablesLocales;
use Tests\Support\FeatureTestCase;

/**
 * hreflang·canonical·언어별 sitemap (#566 3단계).
 *
 * @internal
 */
final class HreflangSitemapTest extends FeatureTestCase
{
    use EnablesLocales;

    private int $koPost;
    private int $enPost;
    private int $koOnlyPost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableLocales();

        $boardId      = (int) (new BoardModel())->getBySlug('notice')['id'];
        $posts        = new PostModel();
        $this->koPost = (int) $posts->insert(['board_id' => $boardId, 'title' => '한글 글', 'content' => '본문', 'locale' => 'ko']);
        $posts->update($this->koPost, ['translation_group_id' => $this->koPost]);
        $this->enPost     = (int) $posts->insert(['board_id' => $boardId, 'title' => 'English post', 'content' => 'body', 'locale' => 'en', 'translation_group_id' => $this->koPost]);
        $this->koOnlyPost = (int) $posts->insert(['board_id' => $boardId, 'title' => '한글만', 'content' => '본문', 'locale' => 'ko']);
        $posts->update($this->koOnlyPost, ['translation_group_id' => $this->koOnlyPost]);

        cache()->delete('seo_sitemap');
        cache()->delete('seo_sitemap_ko');
        cache()->delete('seo_sitemap_en');
    }

    protected function tearDown(): void
    {
        $this->restoreLocales();

        parent::tearDown();
    }

    public function testPostPageListsAlternatesAndSelfCanonical(): void
    {
        $body = $this->get('board/notice/' . $this->koPost)->getBody();

        $this->assertStringContainsString('<link rel="canonical" href="' . base_url('board/notice/' . $this->koPost) . '">', $body);
        $this->assertStringContainsString('<link rel="alternate" hreflang="ko" href="' . base_url('board/notice/' . $this->koPost) . '">', $body);
        $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="' . base_url('en/board/notice/' . $this->enPost) . '">', $body);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="' . base_url('board/notice/' . $this->koPost) . '">', $body);
    }

    public function testTranslatedPostCanonicalUsesItsOwnLocale(): void
    {
        $body = $this->get('en/board/notice/' . $this->enPost)->getBody();

        $this->assertStringContainsString('<link rel="canonical" href="' . base_url('en/board/notice/' . $this->enPost) . '">', $body);
        $this->assertStringContainsString('<html lang="en"', $body);
        $this->assertStringContainsString('<meta property="og:locale" content="en_US">', $body);
    }

    public function testPostWithoutTranslationHasNoHreflang(): void
    {
        $body = $this->get('board/notice/' . $this->koOnlyPost)->getBody();

        $this->assertStringNotContainsString('hreflang=', $body);
    }

    public function testHomeListsAlternatesForEveryLocale(): void
    {
        $body = $this->get('/')->getBody();

        $this->assertStringContainsString('hreflang="ko" href="' . base_url('/') . '"', $body);
        $this->assertStringContainsString('hreflang="en" href="' . base_url('en') . '"', $body);
        $this->assertStringContainsString('hreflang="x-default"', $body);
    }

    public function testPageAlternates(): void
    {
        $pages = new PageModel();
        $ko    = (int) $pages->insert(['slug' => 'hreflang-page', 'title' => '소개', 'status' => 'published']);
        $pages->update($ko, ['translation_group_id' => $ko]);
        $pages->insert(['slug' => 'hreflang-page-en', 'title' => 'About', 'status' => 'published', 'locale' => 'en', 'translation_group_id' => $ko]);

        $body = $this->get('hreflang-page')->getBody();

        $this->assertStringContainsString('hreflang="en" href="' . base_url('en/hreflang-page-en') . '"', $body);
        $this->assertStringContainsString('hreflang="ko" href="' . base_url('hreflang-page') . '"', $body);
    }

    public function testSitemapIsIndexOfPerLocaleSitemaps(): void
    {
        $body = $this->get('sitemap.xml')->getBody();

        $this->assertStringContainsString('<sitemapindex', $body);
        $this->assertStringContainsString('<loc>' . base_url('sitemap-ko.xml') . '</loc>', $body);
        $this->assertStringContainsString('<loc>' . base_url('sitemap-en.xml') . '</loc>', $body);
    }

    public function testLocaleSitemapListsOnlyThatLocaleWithAlternates(): void
    {
        $en = $this->get('sitemap-en.xml')->response()->getBody();
        $ko = $this->get('sitemap-ko.xml')->response()->getBody();

        $this->assertStringContainsString('<loc>' . base_url('en/board/notice/' . $this->enPost) . '</loc>', $en);
        $this->assertStringNotContainsString('<loc>' . base_url('board/notice/' . $this->koPost) . '</loc>', $en);
        $this->assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $en);
        $this->assertStringContainsString('<xhtml:link rel="alternate" hreflang="ko" href="' . base_url('board/notice/' . $this->koPost) . '"/>', $en);

        $this->assertStringContainsString('<loc>' . base_url('board/notice/' . $this->koPost) . '</loc>', $ko);
        $this->assertStringNotContainsString('<loc>' . base_url('en/board/notice/' . $this->enPost) . '</loc>', $ko);
        $this->assertStringContainsString('hreflang="en" href="' . base_url('en/board/notice/' . $this->enPost) . '"', $ko);
    }

    public function testUnknownLocaleSitemapIsNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->get('sitemap-fr.xml');
    }
}
