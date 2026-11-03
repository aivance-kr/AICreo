<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Libraries\TwoFactor;
use App\Models\UserModel;
use OTPHP\TOTP;
use Tests\Support\DatabaseTestCase;

/**
 * 관리자 2단계 인증(TOTP) 라이브러리 (#559).
 *
 * @internal
 */
final class TwoFactorTest extends DatabaseTestCase
{
    private TwoFactor $twoFactor;
    private UserModel $users;
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users     = new UserModel();
        $this->twoFactor = new TwoFactor($this->users);
        $this->secret    = $this->twoFactor->generateSecret();
    }

    private function codeAt(int $offsetSteps = 0): string
    {
        return TOTP::createFromSecret($this->secret)->at(time() + $offsetSteps * 30);
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<string>} 2FA 를 켠 관리자와 복구 코드
     */
    private function enabledAdmin(): array
    {
        $recovery = $this->twoFactor->enable(1, $this->secret, $this->codeAt());
        $this->assertNotNull($recovery);

        return [$this->users->find(1), $recovery];
    }

    public function testEnableRejectsWrongCode(): void
    {
        $this->assertNull($this->twoFactor->enable(1, $this->secret, '000000'));
        $this->assertFalse(TwoFactor::isEnabled($this->users->find(1)));
    }

    public function testEnableStoresSecretEncryptedAndRecoveryHashed(): void
    {
        [$user, $recovery] = $this->enabledAdmin();

        $this->assertTrue(TwoFactor::isEnabled($user));
        $this->assertStringNotContainsString($this->secret, (string) $user['totp_secret']);
        $this->assertCount(TwoFactor::RECOVERY_COUNT, $recovery);
        $this->assertStringNotContainsString($recovery[0], (string) $user['totp_recovery_codes']);
    }

    public function testMatchStepAllowsOneStepSkewOnly(): void
    {
        $now  = 1_700_000_000;
        $totp = TOTP::createFromSecret($this->secret);
        $at   = static fn (int $t): string => $totp->at($t);

        $this->assertNotNull($this->twoFactor->matchStep($this->secret, $at($now - 30), 0, $now));
        $this->assertNull($this->twoFactor->matchStep($this->secret, $at($now - 90), 0, $now));
    }

    public function testAttemptAcceptsCodeButNotReplay(): void
    {
        $this->enabledAdmin();
        $user = $this->users->find(1);

        // 등록에 쓴 코드는 재사용 불가, 다음 구간 코드는 통과, 그 코드를 다시 내면 거부
        $this->assertFalse($this->twoFactor->attempt($user, $this->codeAt()));
        $next = $this->codeAt(1);
        $this->assertTrue($this->twoFactor->attempt($this->users->find(1), $next));
        $this->assertFalse($this->twoFactor->attempt($this->users->find(1), $next));
    }

    public function testRecoveryCodeWorksOnce(): void
    {
        [, $recovery] = $this->enabledAdmin();

        $this->assertTrue($this->twoFactor->attempt($this->users->find(1), strtolower($recovery[0])));
        $this->assertFalse($this->twoFactor->attempt($this->users->find(1), $recovery[0]));
        $this->assertTrue($this->twoFactor->attempt($this->users->find(1), $recovery[1]));
    }

    public function testRepeatedFailuresLockEvenCorrectCode(): void
    {
        $this->enabledAdmin();

        for ($i = 0; $i < TwoFactor::MAX_FAILURES; $i++) {
            $this->assertFalse($this->twoFactor->attempt($this->users->find(1), '111111'));
        }

        $user = $this->users->find(1);
        $this->assertTrue(TwoFactor::isLocked($user));
        $this->assertFalse($this->twoFactor->attempt($user, $this->codeAt(1)));
    }

    public function testRegenerateRecoveryCodesInvalidatesOldOnes(): void
    {
        [, $old] = $this->enabledAdmin();
        $new     = $this->twoFactor->regenerateRecoveryCodes(1);

        $this->assertFalse($this->twoFactor->attempt($this->users->find(1), $old[0]));
        $this->assertTrue($this->twoFactor->attempt($this->users->find(1), $new[0]));
    }

    public function testDisableClearsEverything(): void
    {
        $this->enabledAdmin();
        $this->twoFactor->disable(1);
        $user = $this->users->find(1);

        $this->assertFalse(TwoFactor::isEnabled($user));
        $this->assertNull($user['totp_secret']);
        $this->assertNull($user['totp_recovery_codes']);
    }
}
