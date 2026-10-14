<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SettingModel;
use App\Models\UserModel;
use Tests\Support\FeatureTestCase;

/**
 * 관리자 설정으로 회원가입 온/오프 제어 (기본값 OFF).
 *
 * @internal
 */
final class SignupToggleTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        cache()->delete('site_settings');
    }

    public function testRegisterPageBlockedByDefault(): void
    {
        $result = $this->get('auth/register');

        $result->assertRedirectTo('/auth/login');
        $this->assertSame('현재 회원가입이 비활성화되어 있습니다.', session('error'));
    }

    public function testRegisterProcessBlockedByDefault(): void
    {
        $result = $this->post('auth/register', [
            'email'            => 'blocked@example.com',
            'password'         => 'password123',
            'password_confirm' => 'password123',
            'nickname'         => '차단됨',
        ]);

        $result->assertRedirectTo('/auth/login');
        $this->assertNull((new UserModel())->findByEmail('blocked@example.com'));
    }

    public function testRegisterPageAccessibleWhenEnabled(): void
    {
        (new SettingModel())->saveSettings(['signup_enabled' => '1']);

        $result = $this->get('auth/register');

        $result->assertStatus(200);
    }

    public function testLoginPageHidesRegisterLinkByDefault(): void
    {
        $body = $this->get('auth/login')->getBody();

        $this->assertStringNotContainsString('/auth/register"', $body);
    }

    public function testLoginPageShowsRegisterLinkWhenEnabled(): void
    {
        (new SettingModel())->saveSettings(['signup_enabled' => '1']);

        $body = $this->get('auth/login')->getBody();

        $this->assertStringContainsString('/auth/register"', $body);
    }
}
