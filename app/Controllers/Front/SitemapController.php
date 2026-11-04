<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Libraries\Seo\SitemapCache;
use App\Models\BoardModel;
use App\Models\PageModel;
use App\Models\PostModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use Config\I18n;

/**
 * 동적 sitemap.xml — 홈·발행 페이지·활성 게시판·공개 글.
 * 모든 URL 은 locale_url() 절대경로. 결과는 1시간 캐시(SitemapCache).
 *
 * 다국어를 켜면 sitemap.xml 은 언어별 sitemap-{언어}.xml 을 가리키는 색인이 되고,
 * 번역이 있는 URL 에는 xhtml:link(hreflang) 대체 주소가 붙는다.
 */
class SitemapController extends BaseController
{
    private const CACHE_TTL = 3600;
    private const XHTML_NS  = 'http://www.w3.org/1999/xhtml';

    public function index(): ResponseInterface
    {
        $i18n = config(I18n::class);

        $xml = cache()->remember(
            SitemapCache::INDEX_KEY,
            self::CACHE_TTL,
            fn (): string => $i18n->prefixedLocales() === [] ? $this->build($i18n->defaultLocale, false) : $this->buildIndex($i18n->locales()),
        );

        return $this->xml($xml);
    }

    public function locale(string $locale): ResponseInterface
    {
        $i18n = config(I18n::class);

        if ($i18n->prefixedLocales() === [] || ! in_array($locale, $i18n->locales(), true)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $xml = cache()->remember(SitemapCache::localeKey($locale), self::CACHE_TTL, fn (): string => $this->build($locale, true));

        return $this->xml($xml);
    }

    private function xml(string $xml): ResponseInterface
    {
        return $this->response->setContentType('application/xml')->setBody($xml);
    }

    /**
     * @param list<string> $locales
     */
    private function buildIndex(array $locales): string
    {
        $body = '';

        foreach ($locales as $locale) {
            $body .= '  <sitemap><loc>' . htmlspecialchars(base_url('sitemap-' . $locale . '.xml'), ENT_XML1) . "</loc></sitemap>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . $body
            . "</sitemapindex>\n";
    }

    private function build(string $locale, bool $withAlternates): string
    {
        $i18n    = config(I18n::class);
        $default = $locale === $i18n->defaultLocale;
        $urls    = [];

        // 홈
        $urls[] = ['loc' => locale_url('', $locale), 'alternates' => $withAlternates ? $this->homeAlternates($i18n->locales()) : []];

        // 발행 페이지 (동적 슬러그)
        $pages      = (new PageModel())->getPublished($withAlternates ? null : $locale);
        $pageGroups = $withAlternates ? $this->groupUrls($pages, static fn (array $p): string => locale_url($p['slug'], $p['locale'])) : [];

        foreach ($pages as $page) {
            if ($page['locale'] !== $locale) {
                continue;
            }
            $urls[] = [
                'loc'        => locale_url($page['slug'], $locale),
                'lastmod'    => $page['updated_at'] ?? null,
                'alternates' => $this->alternatesOf($page['translation_group_id'], $pageGroups),
            ];
        }

        // 활성·공개(guest 열람) 게시판 목록 — 비공개 게시판 제외. 다른 언어는 그 언어 글이 있는 게시판만
        $postModel = new PostModel();

        foreach ((new BoardModel())->getActiveBoards() as $board) {
            if ($board['read_permission'] !== 'guest') {
                continue;
            }
            if (! $default && $postModel->getTotalCount((int) $board['id'], null, $locale) === 0) {
                continue;
            }
            $urls[] = [
                'loc'     => locale_url('board/' . $board['slug'], $locale),
                'lastmod' => $board['updated_at'] ?? null,
            ];
        }

        // 태그 목록 — 공개 태그가 있을 때만 (태그별 페이지는 얇은 목록이라 제외, 태그는 아직 언어 구분이 없어 기본 언어만)
        if ($default && $postModel->getPublicTagCounts() !== []) {
            $urls[] = ['loc' => locale_url('tags', $locale)];
        }

        // 공개 글 (비밀글·비활성 게시판 제외)
        $posts      = $postModel->getPublicForSitemap($withAlternates ? null : $locale);
        $postGroups = $withAlternates ? $this->groupUrls($posts, static fn (array $p): string => locale_url('board/' . $p['board_slug'] . '/' . $p['id'], $p['locale'])) : [];

        foreach ($posts as $post) {
            if ($post['locale'] !== $locale) {
                continue;
            }
            $urls[] = [
                'loc'        => locale_url('board/' . $post['board_slug'] . '/' . $post['id'], $locale),
                'lastmod'    => $post['updated_at'] ?? null,
                'alternates' => $this->alternatesOf($post['translation_group_id'], $postGroups),
            ];
        }

        return $this->renderXml($urls, $withAlternates);
    }

    /**
     * @param list<string> $locales
     *
     * @return array<string, string>
     */
    private function homeAlternates(array $locales): array
    {
        $urls = [];

        foreach ($locales as $locale) {
            $urls[$locale] = locale_url('', $locale);
        }

        return $urls;
    }

    /**
     * @param array<int|string, array<string, string>> $byGroup 묶음 id => (언어 => 주소)
     *
     * @return array<string, string> 번역이 둘 이상일 때만
     */
    private function alternatesOf(int|string|null $group, array $byGroup): array
    {
        $urls = $group === null ? [] : ($byGroup[$group] ?? []);

        return count($urls) > 1 ? $urls : [];
    }

    /**
     * 번역 묶음별 언어 => 주소.
     *
     * @param list<array<string, mixed>>             $rows
     * @param callable(array<string, mixed>): string $url
     *
     * @return array<int|string, array<string, string>>
     */
    private function groupUrls(array $rows, callable $url): array
    {
        $byGroup = [];

        foreach ($rows as $row) {
            if ($row['translation_group_id'] !== null) {
                $byGroup[$row['translation_group_id']][$row['locale']] = $url($row);
            }
        }

        return $byGroup;
    }

    /**
     * @param list<array{loc:string,lastmod?:string|null,alternates?:array<string,string>}> $urls
     */
    private function renderXml(array $urls, bool $withAlternates): string
    {
        $body = '';

        foreach ($urls as $url) {
            $body .= "  <url>\n";
            $body .= '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1) . "</loc>\n";
            if (! empty($url['lastmod'])) {
                $body .= '    <lastmod>' . date('c', strtotime($url['lastmod'])) . "</lastmod>\n";
            }

            foreach ($url['alternates'] ?? [] as $lang => $href) {
                $body .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($lang, ENT_XML1) . '" href="' . htmlspecialchars($href, ENT_XML1) . "\"/>\n";
            }
            $body .= "  </url>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . ($withAlternates ? ' xmlns:xhtml="' . self::XHTML_NS . '"' : '') . ">\n"
            . $body
            . "</urlset>\n";
    }
}
