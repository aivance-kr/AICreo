<?php

declare(strict_types=1);

namespace Config;

use App\Filters\AuthFilter;
use App\Filters\BlockedIpFilter;
use App\Filters\RedirectFilter;
use App\Filters\VisitLogFilter;
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;

class Filters extends BaseConfig
{
    /**
     * @var array<string, class-string|list<class-string>>
     */
    public array $aliases = [
        'csrf'      => CSRF::class,
        'toolbar'   => DebugToolbar::class,
        'honeypot'  => Honeypot::class,
        'auth'      => AuthFilter::class,   // ← 추가
        'redirect'  => RedirectFilter::class,
        'visitLog'  => VisitLogFilter::class,
        'blockedIp' => BlockedIpFilter::class,
    ];

    /**
     * @var array<string, mixed>
     */
    public array $globals = [
        'before' => [
            'redirect',
            'csrf'     => ['except' => ['api/*', 'board/image-upload', 'admin/media/upload']],
            'visitLog' => ['except' => VisitLogFilter::EXCLUDED_PATHS],
        ],
        'after' => ['toolbar'],
    ];

    /**
     * 차단 IP 검사는 라우트에 매칭되지 않는 주소(스캐너의 /foo/bar.php 등)에도 적용돼야 하므로
     * 라우트와 무관하게 항상 실행되는 required 필터에 둔다.
     *
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = [
        'before' => [
            'blockedIp',
        ],
        'after' => [],
    ];

    /**
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [];
}
