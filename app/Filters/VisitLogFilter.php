<?php

declare(strict_types=1);

namespace App\Filters;

use App\Models\PageVisitModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 접속 통계 집계용 방문 로그 필터.
 *
 * 크롤러(User-Agent 기반)는 집계에서 제외한다 — sitemap.xml 등 봇 진입 경로는
 * Config\Filters 의 except 로 걸러지지만, 일반 페이지를 도는 봇은 UA 로만 구분된다.
 */
class VisitLogFilter implements FilterInterface
{
    private const BOT_PATTERN = '/bot|crawl|spider|slurp/i';

    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }

        if (strtolower($request->getMethod()) !== 'get') {
            return null;
        }

        $userAgent = $request->getHeaderLine('User-Agent');
        if ($userAgent !== '' && preg_match(self::BOT_PATTERN, $userAgent) === 1) {
            return null;
        }

        $path    = trim($request->getPath(), '/');
        $referer = $request->getServer('HTTP_REFERER');

        (new PageVisitModel())->logVisit(
            $path === '' ? '/' : '/' . $path,
            $request->getIPAddress(),
            $userAgent !== '' ? substr($userAgent, 0, 255) : null,
            $referer !== null ? substr((string) $referer, 0, 500) : null,
        );

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
