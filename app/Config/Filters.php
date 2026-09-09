<?php

declare(strict_types=1);

namespace Config;

use App\Filters\AuthFilter;
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
        'csrf'     => CSRF::class,
        'toolbar'  => DebugToolbar::class,
        'honeypot' => Honeypot::class,
        'auth'     => AuthFilter::class,   // ← 추가
        'redirect' => RedirectFilter::class,
        'visitLog' => VisitLogFilter::class,
    ];

    /**
     * @var array<string, mixed>
     */
    public array $globals = [
        'before' => [
            'redirect',
            'csrf'     => ['except' => ['api/*', 'board/image-upload', 'admin/media/upload']],
            'visitLog' => ['except' => ['admin/*', 'sitemap.xml', 'robots.txt', 'llms.txt', 'indexnow-key.txt']],
        ],
        'after' => ['toolbar'],
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
