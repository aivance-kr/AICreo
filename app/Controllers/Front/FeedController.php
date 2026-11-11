<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Libraries\Seo\LocaleCache;
use App\Models\PostModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 블로그 RSS 2.0 피드 — /feed, /rss.
 * 최신 공개 글(비밀글·비공개 게시판 제외) 상위 20건. 언어별로 해당 언어 글만 담고(/en/feed),
 * 결과는 seo_feed_{언어} 로 1시간 캐시.
 */
class FeedController extends BaseController
{
    private const CACHE_TTL  = 3600;
    private const ITEM_LIMIT = 20;

    public function index(): ResponseInterface
    {
        $locale = $this->request->getLocale();
        $xml    = cache()->remember(LocaleCache::feedKey($locale), self::CACHE_TTL, fn (): string => $this->build($locale));

        return $this->response
            ->setContentType('application/rss+xml')
            ->setBody($xml);
    }

    private function build(string $locale): string
    {
        $settings = (new SettingModel())->getAllAsMap();
        $posts    = (new PostModel())->getLatestPublic(self::ITEM_LIMIT, $locale);

        $items = '';

        foreach ($posts as $post) {
            $link = locale_url('board/' . $post['board_slug'] . '/' . $post['id'], $locale);
            // 엔티티를 풀면 &#1; 같은 XML 금지 제어문자가 생겨 피드 전체가 깨진다 — 출력 전에 제거
            $excerpt = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', post_excerpt($post['content'], 200));
            $pubDate = date('r', strtotime((string) $post['created_at']));

            $items .= "  <item>\n";
            $items .= '    <title>' . htmlspecialchars((string) $post['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</title>\n";
            $items .= '    <link>' . htmlspecialchars($link, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</link>\n";
            $items .= '    <guid>' . htmlspecialchars($link, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</guid>\n";
            $items .= '    <description>' . htmlspecialchars($excerpt, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</description>\n";

            // 게시판 → 카테고리 순으로 <category> 를 낸다(카테고리 없는 글은 게시판만)
            foreach ([$post['board_name'] ?? null, $post['category_name'] ?? null] as $label) {
                if (is_string($label) && $label !== '') {
                    $items .= '    <category>' . htmlspecialchars($label, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</category>\n";
                }
            }
            $items .= '    <pubDate>' . $pubDate . "</pubDate>\n";
            $items .= "  </item>\n";
        }

        $siteName = htmlspecialchars((string) ($settings['site_name'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $siteDesc = htmlspecialchars((string) ($settings['site_desc'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $siteLink = htmlspecialchars(rtrim(locale_url('', $locale), '/'), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0">' . "\n"
            . "<channel>\n"
            . '  <title>' . $siteName . "</title>\n"
            . '  <link>' . $siteLink . "</link>\n"
            . '  <description>' . $siteDesc . "</description>\n"
            . '  <language>' . htmlspecialchars($locale, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</language>\n"
            . $items
            . "</channel>\n"
            . "</rss>\n";
    }
}
