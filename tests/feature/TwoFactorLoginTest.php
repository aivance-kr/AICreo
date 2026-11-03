<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\TwoFactor;
use App\Models\UserModel;
use OTPHP\TOTP;
use Tests\Support\AdminTestCase;

/**
 * 관리자 2단계 인증: 로그인 게이트와 설정 화면 (#559).
 *
 * @internal
 */
final class TwoFactorLoginTest extends AdminTestCase
{
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $twoFactor    = new TwoFactor();
        $this->secret = $twoFactor->generateSecret();
        $twoFactor->enable(1, $this->secret, TOTP::createFromSecret($this->secret)->now());
    }

    private function code(int $offsetSteps = 1): string
    {
        return TOTP::createFromSecret($this->secret)->at(time() + $offsetSteps * 30);
    }

    /**
     * @return array<string, mixed> 비밀번호 단계만 통과한 상태의 세션
     */
    private function pending(): array
    {
        return ['twofa_user_id' => 1, 'twofa_started' => time()];
    }

    private function passwordLogin(): void
    {
        $this->post('auth/login', ['email' => 'admin@example.com', 'password' => 'admin1234!'])
            ->assertRedirectTo('/auth/2fa');
    }

    public function testPasswordLoginDoesNotCompleteSession(): void
    {
        $this->passwordLogin();

        $this->assertNull(session()->get('user_id'));
        $this->get('admin')->assertRedirectTo('/auth/login');
    }

    public function testCorrectCodeCompletesSession(): void
    {
        $this->withSession($this->pending())->post('auth/2fa', ['code' => $this->code()])->assertRedirectTo('/');

        $this->assertSame(1, (int) session()->get('user_id'));
        $this->assertSame('admin', session()->get('user_role'));
        $this->assertNull(session()->get('twofa_user_id'));
    }

    public function testWrongCodeKeepsSessionClosed(): void
    {
        $this->withSession($this->pending())->post('auth/2fa', ['code' => '000000'])->assertRedirectTo('/auth/2fa');

        $this->assertNull(session()->get('user_id'));
    }

    public function testLockoutEndsPendingLogin(): void
    {
        for ($i = 0; $i < TwoFactor::MAX_FAILURES; $i++) {
            $this->withSession($this->pending())->post('auth/2fa', ['code' => '000000']);
        }

        $this->assertTrue(TwoFactor::isLocked((new UserModel())->find(1)));
        $this->withSession($this->pending())->post('auth/2fa', ['code' => $this->code()])->assertRedirectTo('/auth/2fa');
        $this->assertNull(session()->get('user_id'));
    }

    public function testTwoFactorPageWithoutPendingLoginRedirects(): void
    {
        $this->get('auth/2fa')->assertRedirectTo('/auth/login');
    }

    public function testMemberLoginIsUnaffected(): void
    {
        $id = (new UserModel())->insert([
            'username' => 'member@example.com', 'email' => 'member@example.com', 'nickname' => '회원',
            'password' => password_hash('member1234!', PASSWORD_DEFAULT), 'role' => 'member', 'is_active' => 1,
        ]);

        $this->post('auth/login', ['email' => 'member@example.com', 'password' => 'member1234!'])->assertRedirectTo('/');
        $this->assertSame((int) $id, (int) session()->get('user_id'));
    }

    public function testDisableRequiresPasswordAndCode(): void
    {
        $this->withSession($this->adminSession)->post('admin/security/disable', ['code' => $this->code(), 'password' => 'wrong'])
            ->assertRedirectTo('/admin/security');
        $this->assertTrue(TwoFactor::isEnabled((new UserModel())->find(1)));

        $this->withSession($this->adminSession)->post('admin/security/disable', ['code' => $this->code(), 'password' => 'admin1234!'])
            ->assertRedirectTo('/admin/security');
        $this->assertFalse(TwoFactor::isEnabled((new UserModel())->find(1)));
    }

    public function testSecurityPageShowsQrWhenDisabled(): void
    {
        (new TwoFactor())->disable(1);

        $result = $this->withSession($this->adminSession)->get('admin/security');

        $result->assertStatus(200);
        $this->assertStringContainsString('<svg', (string) $result->getBody());
    }

    /**
     * @return array<string, mixed> 등록 대기 중(암호화된 시크릿, 10분 유효) 관리자 세션
     */
    private function setupSession(string $secret): array
    {
        return $this->adminSession + [
            'totp_setup_secret' => base64_encode(service('encrypter')->encrypt($secret)),
            '__ci_vars'         => ['totp_setup_secret' => time() + 600],
        ];
    }

    public function testEnableFlowActivatesWithValidCodeAndPassword(): void
    {
        (new TwoFactor())->disable(1);
        $setup = (new TwoFactor())->generateSecret();

        $this->withSession($this->setupSession($setup))
            ->post('admin/security/enable', ['code' => TOTP::createFromSecret($setup)->now(), 'password' => 'admin1234!'])
            ->assertRedirectTo('/admin/security');

        $this->assertTrue(TwoFactor::isEnabled((new UserModel())->find(1)));
    }

    public function testEnableRejectsWrongPassword(): void
    {
        (new TwoFactor())->disable(1);
        $setup = (new TwoFactor())->generateSecret();

        $this->withSession($this->setupSession($setup))
            ->post('admin/security/enable', ['code' => TOTP::createFromSecret($setup)->now(), 'password' => 'wrong'])
            ->assertRedirectTo('/admin/security');

        $this->assertFalse(TwoFactor::isEnabled((new UserModel())->find(1)));
    }

    public function testRecoveryReissueRequiresPassword(): void
    {
        $this->withSession($this->adminSession)->post('admin/security/recovery', ['code' => $this->code(), 'password' => 'wrong'])
            ->assertRedirectTo('/admin/security');
        $before = (new UserModel())->find(1)['totp_recovery_codes'];

        $this->withSession($this->adminSession)->post('admin/security/recovery', ['code' => $this->code(), 'password' => 'admin1234!'])
            ->assertRedirectTo('/admin/security');

        $this->assertNotSame($before, (new UserModel())->find(1)['totp_recovery_codes']);
    }
}
