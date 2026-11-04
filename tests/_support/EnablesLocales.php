<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Config\Factories;
use CodeIgniter\Router\RouteCollection;
use Config\I18n;
use Config\Services;
use Locale;

/**
 * Feature 테스트에서 다국어(ko,en 등)를 켠다. 라우트는 I18n 설정으로 등록되므로 새 라우트 컬렉션으로 다시 읽고,
 * 끝나면 다른 테스트로 새지 않게 원래대로 되돌린다. setUp 의 parent::setUp() 뒤에 enableLocales() 를, tearDown 에서 restoreLocales() 를 부른다.
 */
trait EnablesLocales
{
    private RouteCollection $originalRoutes;

    protected function enableLocales(string $enabled = 'ko,en'): void
    {
        $config          = new I18n();
        $config->enabled = $enabled;
        Factories::injectMock('config', 'I18n', $config);

        $app                   = config('App');
        $app->supportedLocales = $config->locales();
        Factories::injectMock('config', 'App', $app);

        $this->originalRoutes = service('routes');
        Services::injectMock('routes', new RouteCollection(service('locator'), config('Modules'), config('Routing')));
        Services::resetSingle('request');
        Services::resetSingle('router');
    }

    protected function restoreLocales(): void
    {
        Factories::reset('config');
        Services::injectMock('routes', $this->originalRoutes);
        Services::resetSingle('router');
        Locale::setDefault('ko');
    }
}
