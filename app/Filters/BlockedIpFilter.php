<?php

declare(strict_types=1);

namespace App\Filters;

use App\Models\BlockedIpModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 차단된 IP 의 모든 요청을 403 으로 막는다.
 */
class BlockedIpFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }

        if (! (new BlockedIpModel())->isBlocked($request->getIPAddress())) {
            return null;
        }

        return service('response')->setStatusCode(403)->setBody('Forbidden');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
