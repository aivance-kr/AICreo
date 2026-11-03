<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Config\Factories;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Router\RouteCollection;
use Config\I18n;
use Config\Services;
use Locale;
use Tests\Support\FeatureTestCase;

/**
 * 언어 접두어(/en/...) 라우팅과 <html lang> (#566 1단계).
 *
 * @internal
 */
final class LocaleFilterTest extends FeatureTestCase
{
    private RouteCollection $originalRoutes;

    protected function setUp(): void
    {
        parent::setUp();

        // 다국어 설정을 켠 뒤 라우트 파일을 새로 읽는다 (라우트가 I18n 설정으로 등록되므로)
        $config          = new I18n();
        $config->enabled = 'ko,en';
        Factories::injectMock('config', 'I18n', $config);

        $app                   = config('App');
        $app->supportedLocales = $config->locales();
        Factories::injectMock('config', 'App', $app);

        $this->originalRoutes = service('routes');
        Services::injectMock('routes', new RouteCollection(service('locator'), config('Modules'), config('Routing')));
        Services::resetSingle('request');
        Services::resetSingle('router');
    }

    protected function tearDown(): void
    {
        // 다국어 라우트·요청이 다른 테스트로 새지 않게 원래 설정으로 되돌린다
        Factories::reset('config');
        Services::injectMock('routes', $this->originalRoutes);
        Services::resetSingle('router');
        Locale::setDefault('ko');

        parent::tearDown();
    }

    public function testRootStaysDefaultLocale(): void
    {
        $result = $this->get('/');

        $result->assertStatus(200);
        $this->assertStringContainsString('<html lang="ko"', $result->getBody());
    }

    public function testPrefixedHomeUsesThatLocale(): void
    {
        $result = $this->get('en');

        $result->assertStatus(200);
        $this->assertStringContainsString('<html lang="en"', $result->getBody());
    }

    public function testPrefixedBoardListRoutes(): void
    {
        $this->get('en/board/notice')->assertStatus(200);
    }

    public function testDefaultLocalePrefixRedirectsToCanonical(): void
    {
        $result = $this->get('ko/board/notice');

        $result->assertStatus(301);
        $this->assertSame(base_url('board/notice'), $result->getRedirectUrl());
    }

    public function testUnknownLocalePrefixIsNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->get('fr/board/notice');
    }

    public function testPlainPageSlugStillRoutes(): void
    {
        // 접두어가 아닌 단일 세그먼트는 기존 catch-all(페이지)로 간다 — 없는 페이지면 404
        $this->expectException(PageNotFoundException::class);

        $this->get('no-such-page');
    }
}
