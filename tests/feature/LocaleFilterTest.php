<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\EnablesLocales;
use Tests\Support\FeatureTestCase;

/**
 * 언어 접두어(/en/...) 라우팅과 <html lang> (#566 1단계).
 *
 * @internal
 */
final class LocaleFilterTest extends FeatureTestCase
{
    use EnablesLocales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableLocales();
    }

    protected function tearDown(): void
    {
        $this->restoreLocales();

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
