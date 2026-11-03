<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use Config\I18n;
use Config\Services;

/**
 * @internal
 */
final class I18nConfigTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('i18n');
    }

    protected function tearDown(): void
    {
        Factories::reset('config');
        parent::tearDown();
    }

    private function useLocales(string $enabled): void
    {
        $config          = new I18n();
        $config->enabled = $enabled;
        Factories::injectMock('config', 'I18n', $config);

        $app                   = config('App');
        $app->supportedLocales = $config->locales();
        Factories::injectMock('config', 'App', $app);

        // 요청 객체는 생성 시점의 지원 로케일을 쓰므로 새로 만든다
        Services::resetSingle('request');
    }

    public function testDefaultsToSingleKoreanLocale(): void
    {
        $this->useLocales('ko');
        $config = config(I18n::class);

        $this->assertSame('ko', $config->defaultLocale);
        $this->assertSame(['ko'], $config->locales());
        $this->assertSame([], $config->prefixedLocales());
    }

    public function testParsesCommaListAndDropsInvalidCodes(): void
    {
        $this->useLocales(' ko, en ,EN,zh-CN,bad_code,,');
        $config = config(I18n::class);

        $this->assertSame(['ko', 'en', 'zh-cn'], $config->locales());
        $this->assertSame(['en', 'zh-cn'], $config->prefixedLocales());
    }

    public function testDefaultLocaleIsAlwaysIncluded(): void
    {
        $this->useLocales('en');
        $config = config(I18n::class);

        $this->assertSame(['ko', 'en'], $config->locales());
    }

    public function testLocaleUrlOmitsPrefixForDefaultLocale(): void
    {
        $this->useLocales('ko,en');

        $this->assertSame(base_url('board/notice'), locale_url('board/notice', 'ko'));
        $this->assertSame(base_url('en/board/notice'), locale_url('board/notice', 'en'));
        $this->assertSame(base_url('en'), locale_url('', 'en'));
        $this->assertSame(base_url('en'), locale_url('/', 'en'));
    }

    public function testLocaleUrlIgnoresUnsupportedLocale(): void
    {
        $this->useLocales('ko,en');

        $this->assertSame(base_url('about'), locale_url('about', 'fr'));
    }

    public function testHtmlLangUsesRequestLocale(): void
    {
        $this->useLocales('ko,en');

        $this->assertSame('ko', html_lang());
        service('request')->setLocale('en');
        $this->assertSame('en', html_lang());
    }
}
