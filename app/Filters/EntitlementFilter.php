<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Plugin\AccessLevel;
use App\Models\UserPluginModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 플러그인 접근의 유일한 관문.
 *
 * 사용법: $routes->get('my/quotes', '...', ['filter' => 'entitlement:quote']);
 *
 * auth:member 를 함께 걸지 않는다 — 이 필터가 로그인 검사도 포함한다.
 * 관문이 둘이면 플러그인 작성자가 하나를 빠뜨릴 수 있다.
 *
 * 판정은 하지 않는다. Entitlement::accessLevel() 의 결과를 받아 라우팅만 한다 —
 * 판정 로직이 두 곳에 생기지 않게.
 */
class EntitlementFilter implements FilterInterface
{
    /**
     * 상태를 바꾸는 HTTP 메서드.
     *
     * 만료된 구독은 이 메서드들만 막는다. 그래서 "GET 으로 상태를 바꾸지
     * 않는다"가 플러그인 작성 규약이다 — GET 핸들러가 DB 를 쓰면 만료
     * 구독자가 그 경로로 쓰기를 계속할 수 있다.
     *
     * @var list<string>
     */
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $pluginKey = $arguments[0] ?? '';

        if ($pluginKey === '') {
            return redirect()->to('/')->with('error', '잘못된 접근입니다.');
        }

        $userId = session()->get('user_id');

        if (! is_int($userId) && ! (is_string($userId) && ctype_digit($userId))) {
            return redirect()->to('/auth/login')->with('error', '로그인이 필요합니다.');
        }

        $entitlement = (new UserPluginModel())->findEntitlement((int) $userId, $pluginKey);
        $access      = $entitlement?->accessLevel() ?? AccessLevel::None;

        if ($access === AccessLevel::None) {
            return redirect()->to('/my/plugins')->with('error', '이 기능은 구매가 필요합니다.');
        }

        if (! $access->canWrite() && $this->isWrite($request)) {
            return redirect()->to('/my/plugins')
                ->with('error', '구독이 만료되어 조회만 가능합니다. 갱신 후 이용해 주세요.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): null
    {
        return null;
    }

    private function isWrite(RequestInterface $request): bool
    {
        return in_array(strtoupper($request->getMethod()), self::WRITE_METHODS, true);
    }
}
