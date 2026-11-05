<?php

declare(strict_types=1);

namespace Tests\Support\Controllers;

use CodeIgniter\Controller;

/**
 * EntitlementFilter 검증 전용 — 필터를 통과했는지만 알려준다.
 *
 * @internal
 */
final class ProbeController extends Controller
{
    public function index(): string
    {
        return 'PROBE';
    }
}
