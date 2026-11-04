<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\I18n;

/**
 * 경로 첫 세그먼트가 사용 언어 코드면 그 언어로 요청 로케일을 정한다 (`/en/board/notice`).
 * 기본 언어 접두어(`/ko/...`)는 중복 URL 이므로 접두어 없는 주소로 301 보낸다.
 * 라우트는 언어별 리터럴 그룹으로 등록돼 있어(Routes.php) 여기서는 로케일만 정한다.
 */
class LocaleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }

        $config = config(I18n::class);

        // 다국어를 켜지 않았으면 기존 동작 그대로 (예: 슬러그가 ko 인 페이지)
        if ($config->prefixedLocales() === []) {
            return null;
        }

        $path    = trim($request->getUri()->getPath(), '/');
        $segment = strtolower(explode('/', $path, 2)[0]);

        if ($segment === $config->defaultLocale) {
            if (strtolower($request->getMethod()) !== 'get') {
                return null;
            }

            $rest  = (string) (explode('/', $path, 2)[1] ?? '');
            $query = $request->getUri()->getQuery();

            return redirect()->to(base_url($rest) . ($query !== '' ? '?' . $query : ''), 301);
        }

        if (in_array($segment, $config->prefixedLocales(), true)) {
            $request->setLocale($segment);
            // lang() 이 쓰는 언어 서비스도 같은 언어로 맞춘다 (이미 만들어졌을 수 있다)
            service('language')->setLocale($request->getLocale());
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
