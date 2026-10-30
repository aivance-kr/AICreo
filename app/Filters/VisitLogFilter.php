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
 * EXCLUDED_PATHS 로 걸러지지만, 일반 페이지를 도는 봇은 UA 로만 구분된다.
 */
class VisitLogFilter implements FilterInterface
{
    /**
     * 방문 로그에서 제외하는 경로 (Config\Filters 의 except 와 record() 가 함께 쓴다)
     */
    public const EXCLUDED_PATHS = ['admin/*', 'sitemap.xml', 'robots.txt', 'llms.txt', 'indexnow-key.txt', 'og/*', 'newsletter/*'];

    private const BOT_PATTERN = '/bot|crawl|spider|slurp/i';

    /**
     * 이번 요청에서 기록한 방문 로그 id (404 표시용). 기록하지 않았으면 null
     */
    public static ?int $currentVisitId = null;

    public function before(RequestInterface $request, $arguments = null)
    {
        self::$currentVisitId = null;

        if ($request instanceof IncomingRequest) {
            self::$currentVisitId = self::record($request);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /**
     * 집계 대상 요청이면 방문 로그를 남기고 id 를 돌려준다. 대상이 아니면 null.
     *
     * 라우트에 매칭되지 않은 404 는 before 필터가 돌지 않으므로 예외 핸들러에서도 호출한다.
     */
    public static function record(IncomingRequest $request): ?int
    {
        if (strtolower($request->getMethod()) !== 'get') {
            return null;
        }

        $userAgent = $request->getHeaderLine('User-Agent');
        if ($userAgent !== '' && preg_match(self::BOT_PATTERN, $userAgent) === 1) {
            return null;
        }

        $path = trim($request->getPath(), '/');

        foreach (self::EXCLUDED_PATHS as $pattern) {
            if (fnmatch($pattern, $path)) {
                return null;
            }
        }

        $referer = $request->getServer('HTTP_REFERER');

        return (new PageVisitModel())->logVisit(
            $path === '' ? '/' : '/' . $path,
            $request->getIPAddress(),
            $userAgent !== '' ? substr($userAgent, 0, 255) : null,
            $referer !== null ? substr((string) $referer, 0, 500) : null,
        );
    }
}
