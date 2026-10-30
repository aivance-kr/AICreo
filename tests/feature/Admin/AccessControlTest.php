<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class AccessControlTest extends AdminTestCase
{
    #[DataProvider('adminRouteProvider')]
    public function testGuestIsRedirectedToLogin(string $route): void
    {
        $result = $this->get($route);

        $result->assertRedirectTo('/auth/login');
    }

    #[DataProvider('adminRouteProvider')]
    public function testMemberIsDeniedAdminArea(string $route): void
    {
        $result = $this->withSession($this->memberSession)->get($route);

        // 관리자가 아니면 redirect()->back() (로그인 페이지 진입은 아님)
        $result->assertStatus(302);
        $this->assertStringNotContainsString('/auth/login', (string) $result->getRedirectUrl());
    }

    #[DataProvider('adminRouteProvider')]
    public function testAdminCanAccess(string $route): void
    {
        $result = $this->withSession($this->adminSession)->get($route);

        $result->assertStatus(200);
    }

    /**
     * @return list<list<string>>
     */
    public static function adminRouteProvider(): iterable
    {
        return [
            ['admin/dashboard'],
            ['admin/pages'],
            ['admin/boards'],
            ['admin/menus'],
            ['admin/inquiries'],
            ['admin/subscribers'],
            ['admin/subscribers/export'],
            ['admin/users'],
            ['admin/settings'],
        ];
    }
}
