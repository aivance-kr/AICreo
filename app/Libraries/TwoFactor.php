<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\UserModel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use OTPHP\TOTP;

/**
 * 관리자 2단계 인증(TOTP, RFC 6238) 처리.
 *
 * - 시크릿은 CI4 Encrypter 로 암호화해 저장한다(encryption.key 필요). 로그에 남기지 않는다.
 * - 같은 코드는 한 번만 인정(totp_last_step)하고, 연속 실패하면 잠근다.
 * - 복구 코드는 해시만 저장하고 쓰면 지운다.
 */
class TwoFactor
{
    public const MAX_FAILURES   = 5;
    public const LOCK_MINUTES   = 15;
    public const RECOVERY_COUNT = 8;
    private const PERIOD        = 30;

    // 헷갈리는 글자(0/O, 1/I)를 뺀 복구 코드 문자
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly UserModel $userModel = new UserModel())
    {
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function isEnabled(array $user): bool
    {
        return ! empty($user['totp_secret']) && ! empty($user['totp_enabled_at']);
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function isLocked(array $user): bool
    {
        return ! empty($user['totp_locked_until']) && strtotime((string) $user['totp_locked_until']) > time();
    }

    public static function encryptionReady(): bool
    {
        return (string) config('Encryption')->key !== '';
    }

    public function generateSecret(): string
    {
        return TOTP::generate()->getSecret();
    }

    public function provisioningUri(string $secret, string $account, string $issuer): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($account);
        $totp->setIssuer($issuer);

        return $totp->getProvisioningUri();
    }

    /**
     * 등록용 QR 코드(인라인 SVG). 시크릿이 외부 QR 서비스로 나가지 않게 서버에서 만든다.
     */
    public function qrSvg(string $uri): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd()));

        return (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $writer->writeString($uri));
    }

    /**
     * 등록 확인: 사용자가 입력한 첫 코드가 맞으면 시크릿을 암호화해 저장하고 복구 코드(평문)를 돌려준다.
     *
     * @return list<string>|null 코드가 틀리면 null
     */
    public function enable(int $userId, string $secret, string $code): ?array
    {
        $step = $this->matchStep($secret, $code, 0);
        if ($step === null) {
            return null;
        }

        $recovery = $this->newRecoveryCodes();
        $this->userModel->update($userId, [
            'totp_secret'         => $this->encrypt($secret),
            'totp_enabled_at'     => date('Y-m-d H:i:s'),
            'totp_recovery_codes' => json_encode(array_map($this->hashRecovery(...), $recovery), JSON_THROW_ON_ERROR),
            'totp_last_step'      => $step,
            'totp_failures'       => 0,
            'totp_locked_until'   => null,
        ]);

        return $recovery;
    }

    public function disable(int $userId): void
    {
        $this->userModel->update($userId, [
            'totp_secret'         => null,
            'totp_enabled_at'     => null,
            'totp_recovery_codes' => null,
            'totp_last_step'      => null,
            'totp_failures'       => 0,
            'totp_locked_until'   => null,
        ]);
    }

    /**
     * 복구 코드를 새로 발급한다(기존 코드는 무효). 평문은 이때만 볼 수 있다.
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(int $userId): array
    {
        $recovery = $this->newRecoveryCodes();
        $this->userModel->update($userId, [
            'totp_recovery_codes' => json_encode(array_map($this->hashRecovery(...), $recovery), JSON_THROW_ON_ERROR),
        ]);

        return $recovery;
    }

    /**
     * 로그인·민감 작업용 코드 검증. TOTP 6자리 또는 복구 코드를 받는다.
     * 실패가 쌓이면 잠그고, 잠긴 동안에는 맞는 코드도 거부한다.
     *
     * @param array<string, mixed> $user
     */
    public function attempt(array $user, string $input): bool
    {
        if (! self::isEnabled($user) || self::isLocked($user)) {
            return false;
        }

        $normalized = strtoupper((string) preg_replace('/[\s-]+/', '', $input));
        $ok         = ctype_digit($normalized) && strlen($normalized) === 6
            ? $this->consumeTotp($user, $normalized)
            : $this->consumeRecovery($user, $normalized);

        $ok ? $this->resetFailures((int) $user['id']) : $this->recordFailure($user);

        return $ok;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function consumeTotp(array $user, string $code): bool
    {
        $step = $this->matchStep($this->decrypt((string) $user['totp_secret']), $code, (int) ($user['totp_last_step'] ?? 0));
        if ($step === null) {
            return false;
        }

        // 동시에 같은 코드를 두 번 내도 한 번만 통과하도록 조건부 갱신
        $this->userModel->builder()
            ->where('id', $user['id'])
            ->groupStart()->where('totp_last_step IS NULL')->orWhere('totp_last_step <', $step)->groupEnd()
            ->update(['totp_last_step' => $step]);

        return $this->userModel->db->affectedRows() > 0;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function consumeRecovery(array $user, string $code): bool
    {
        if ($code === '' || empty($user['totp_recovery_codes'])) {
            return false;
        }

        $stored = (string) $user['totp_recovery_codes'];
        $hashes = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        $hash   = $this->hashRecovery($code);
        $found  = false;
        $left   = [];

        foreach ($hashes as $candidate) {
            if (! $found && hash_equals((string) $candidate, $hash)) {
                $found = true;

                continue;
            }
            $left[] = $candidate;
        }

        if (! $found) {
            return false;
        }

        // 같은 복구 코드를 동시에 두 번 써도 한 번만 통과하도록 읽은 값 기준 조건부 갱신
        $this->userModel->builder()
            ->where('id', $user['id'])
            ->where('totp_recovery_codes', $stored)
            ->update(['totp_recovery_codes' => json_encode($left, JSON_THROW_ON_ERROR)]);

        return $this->userModel->db->affectedRows() > 0;
    }

    /**
     * 허용 오차 ±1 구간에서 맞는 time-step 을 찾는다. $afterStep 이하(이미 쓴 코드)는 제외.
     */
    public function matchStep(string $secret, string $code, int $afterStep, ?int $now = null): ?int
    {
        $totp    = TOTP::createFromSecret($secret);
        $current = intdiv($now ?? time(), self::PERIOD);

        for ($step = $current - 1; $step <= $current + 1; $step++) {
            if ($step > $afterStep && hash_equals($totp->at($step * self::PERIOD), $code)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function recordFailure(array $user): void
    {
        $id = (int) $user['id'];

        // 동시 요청이 같은 스냅샷에서 덮어쓰지 못하게 DB 에서 원자적으로 올리고, 잠금 여부도 DB 값으로 판단
        $this->userModel->builder()->where('id', $id)->set('totp_failures', 'totp_failures + 1', false)->update();
        $failures = (int) ($this->userModel->builder()->select('totp_failures')->where('id', $id)->get()->getRowArray()['totp_failures'] ?? 0);

        if ($failures >= self::MAX_FAILURES) {
            $this->userModel->update($id, [
                'totp_failures'     => 0,
                'totp_locked_until' => date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60),
            ]);
        }
    }

    private function resetFailures(int $userId): void
    {
        $this->userModel->update($userId, ['totp_failures' => 0, 'totp_locked_until' => null]);
    }

    /**
     * @return list<string> XXXX-XXXX-XXXX 형식
     */
    private function newRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_COUNT; $i++) {
            $raw = '';

            for ($j = 0; $j < 12; $j++) {
                $raw .= self::RECOVERY_ALPHABET[random_int(0, strlen(self::RECOVERY_ALPHABET) - 1)];
            }
            $codes[] = implode('-', str_split($raw, 4));
        }

        return $codes;
    }

    // 엔트로피가 충분한 무작위 코드라 느린 해시는 필요 없다
    private function hashRecovery(string $code): string
    {
        return hash('sha256', strtoupper((string) preg_replace('/[\s-]+/', '', $code)));
    }

    private function encrypt(string $secret): string
    {
        return base64_encode(service('encrypter')->encrypt($secret));
    }

    private function decrypt(string $stored): string
    {
        return service('encrypter')->decrypt((string) base64_decode($stored, true));
    }
}
