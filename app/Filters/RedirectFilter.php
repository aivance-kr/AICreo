<?php

declare(strict_types=1);

namespace App\Filters;

use App\Models\RedirectModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 워드프레스 등 구주소(쿼리스트링 퍼머링크 포함) → 새 주소 301/302 리다이렉트.
 * 라우팅 매칭 전에 실행 — `/?p=191` 처럼 경로만으로는 catch-all 라우트가
 * 못 잡는 쿼리스트링 기반 구주소도 여기서 가로챈다.
 */
class RedirectFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtolower($request->getMethod()) !== 'get') {
            return null;
        }

        $uri     = $request->getUri();
        $oldPath = $uri->getPath() === '' ? '/' : $uri->getPath();
        $oldPath .= $uri->getQuery() !== '' ? '?' . $uri->getQuery() : '';

        $redirect = (new RedirectModel())->findByOldPath($oldPath);
        if ($redirect === null) {
            return null;
        }

        return redirect()->to($redirect['new_path'], (int) $redirect['status_code']);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
