<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Models\PostModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 블로그 RSS 2.0 피드 — /feed, /rss.
 * 최신 공개 글(비밀글·비공개 게시판 제외) 상위 20건. 결과는 seo_feed 로 1시간 캐시.
 */
class FeedController extends BaseController
{
    private const CACHE_KEY  = 'seo_feed';
    private const CACHE_TTL  = 3600;
    private const ITEM_LIMIT = 20;

    public function index(): ResponseInterface
    {
        $xml = cache()->remember(self::CACHE_KEY, self::CACHE_TTL, fn (): string => $this->build());

        return $this->response
            ->setContentType('application/rss+xml')
            ->setBody($xml);
    }

    private function build(): string
    {
        $settings = (new SettingModel())->getAllAsMap();
        $posts    = (new PostModel())->getLatestPublic(self::ITEM_LIMIT);

        $items = '';

        foreach ($posts as $post) {
            $link    = base_url('board/' . $post['board_slug'] . '/' . $post['id']);
            $excerpt = mb_substr(trim(strip_tags((string) $post['content'])), 0, 200);
            $pubDate = date('r', strtotime((string) $post['created_at']));

            $items .= "  <item>\n";
            $items .= '    <title>' . htmlspecialchars((string) $post['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</title>\n";
            $items .= '    <link>' . htmlspecialchars($link, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</link>\n";
            $items .= '    <guid>' . htmlspecialchars($link, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</guid>\n";
            $items .= '    <description>' . htmlspecialchars($excerpt, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</description>\n";
            $items .= '    <pubDate>' . $pubDate . "</pubDate>\n";
            $items .= "  </item>\n";
        }

        $siteName = htmlspecialchars((string) ($settings['site_name'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $siteDesc = htmlspecialchars((string) ($settings['site_desc'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $siteLink = htmlspecialchars(rtrim(base_url('/'), '/'), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0">' . "\n"
            . "<channel>\n"
            . '  <title>' . $siteName . "</title>\n"
            . '  <link>' . $siteLink . "</link>\n"
            . '  <description>' . $siteDesc . "</description>\n"
            . "  <language>ko</language>\n"
            . $items
            . "</channel>\n"
            . "</rss>\n";
    }
}
