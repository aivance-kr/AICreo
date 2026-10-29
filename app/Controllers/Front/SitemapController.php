<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Models\BoardModel;
use App\Models\PageModel;
use App\Models\PostModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 동적 sitemap.xml — 홈·발행 페이지·활성 게시판·공개 글.
 * 모든 URL 은 base_url() 절대경로. 결과는 seo_sitemap 으로 1시간 캐시.
 */
class SitemapController extends BaseController
{
    private const CACHE_KEY = 'seo_sitemap';
    private const CACHE_TTL = 3600;

    public function index(): ResponseInterface
    {
        $xml = cache()->remember(self::CACHE_KEY, self::CACHE_TTL, fn (): string => $this->build());

        return $this->response
            ->setContentType('application/xml')
            ->setBody($xml);
    }

    private function build(): string
    {
        $urls = [];

        // 홈
        $urls[] = ['loc' => base_url('/')];

        // 발행 페이지 (동적 슬러그)
        foreach ((new PageModel())->getPublished() as $page) {
            $urls[] = [
                'loc'     => base_url($page['slug']),
                'lastmod' => $page['updated_at'] ?? null,
            ];
        }

        // 활성·공개(guest 열람) 게시판 목록 — 비공개 게시판은 제외
        foreach ((new BoardModel())->getActiveBoards() as $board) {
            if ($board['read_permission'] !== 'guest') {
                continue;
            }
            $urls[] = [
                'loc'     => base_url('board/' . $board['slug']),
                'lastmod' => $board['updated_at'] ?? null,
            ];
        }

        // 태그 목록 — 공개 태그가 있을 때만 (태그별 페이지는 얇은 목록이라 제외)
        if ((new PostModel())->getPublicTagCounts() !== []) {
            $urls[] = ['loc' => base_url('tags')];
        }

        // 공개 글 (비밀글·비활성 게시판 제외)
        foreach ((new PostModel())->getPublicForSitemap() as $post) {
            $urls[] = [
                'loc'     => base_url('board/' . $post['board_slug'] . '/' . $post['id']),
                'lastmod' => $post['updated_at'] ?? null,
            ];
        }

        $body = '';

        foreach ($urls as $url) {
            $body .= "  <url>\n";
            $body .= '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1) . "</loc>\n";
            if (! empty($url['lastmod'])) {
                $body .= '    <lastmod>' . date('c', strtotime($url['lastmod'])) . "</lastmod>\n";
            }
            $body .= "  </url>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . $body
            . "</urlset>\n";
    }
}
