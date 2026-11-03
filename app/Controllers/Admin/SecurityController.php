<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\TwoFactor;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * 관리자 본인 계정의 2단계 인증(TOTP) 설정·해제·복구 코드 재발급.
 */
class SecurityController extends BaseController
{
    private const URL       = '/admin/security';
    private const SETUP_KEY = 'totp_setup_secret';
    private const SETUP_TTL = 600;

    private readonly UserModel $userModel;
    private readonly TwoFactor $twoFactor;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->twoFactor = new TwoFactor($this->userModel);
    }

    public function index(): string
    {
        $user    = $this->currentUser();
        $enabled = TwoFactor::isEnabled($user);
        $ready   = TwoFactor::encryptionReady();
        $qr      = null;
        $secret  = null;

        if (! $enabled && $ready) {
            $secret = $this->setupSecret();
            if ($secret === null) {
                $secret = $this->twoFactor->generateSecret();
                session()->setTempdata(self::SETUP_KEY, base64_encode(service('encrypter')->encrypt($secret)), self::SETUP_TTL);
            }

            $issuer = (string) ($this->viewData['settings']['site_name'] ?? 'AICreo');
            $qr     = $this->twoFactor->qrSvg($this->twoFactor->provisioningUri($secret, (string) $user['email'], $issuer));
        }

        return $this->render('admin/security/index', [
            'enabled'       => $enabled,
            'ready'         => $ready,
            'qr'            => $qr,
            'secret'        => $secret,
            'hasPassword'   => ! empty($user['password']),
            'recoveryCodes' => session()->getFlashdata('recoveryCodes'),
            'remaining'     => $enabled ? count(json_decode((string) ($user['totp_recovery_codes'] ?? '[]'), true) ?: []) : 0,
        ]);
    }

    public function enable(): ResponseInterface
    {
        $user   = $this->currentUser();
        $secret = TwoFactor::encryptionReady() ? $this->setupSecret() : null;
        $code   = $this->request->getPost('code');

        if (TwoFactor::isEnabled($user) || $secret === null) {
            return redirect()->to(self::URL);
        }

        // 세션을 탈취한 쪽이 자기 앱을 묶어 본인을 잠그지 못하도록 비밀번호 재확인
        if (! $this->passwordOk($user)) {
            return redirect()->to(self::URL)->with('error', '비밀번호가 올바르지 않습니다.');
        }

        $recovery = is_string($code) ? $this->twoFactor->enable((int) $user['id'], $secret, trim($code)) : null;
        if ($recovery === null) {
            return redirect()->to(self::URL)->with('error', '확인 코드가 올바르지 않습니다. 앱에 표시된 6자리를 입력해 주세요.');
        }

        session()->removeTempdata(self::SETUP_KEY);

        return redirect()->to(self::URL)
            ->with('success', '2단계 인증을 켰습니다. 복구 코드를 안전한 곳에 보관하세요.')
            ->with('recoveryCodes', $recovery);
    }

    public function disable(): ResponseInterface
    {
        $user = $this->currentUser();

        if (! $this->confirmed($user, true)) {
            return redirect()->to(self::URL)->with('error', '비밀번호 또는 인증 코드가 올바르지 않습니다.');
        }

        $this->twoFactor->disable((int) $user['id']);
        session()->removeTempdata(self::SETUP_KEY);

        return redirect()->to(self::URL)->with('success', '2단계 인증을 껐습니다.');
    }

    public function recovery(): ResponseInterface
    {
        $user = $this->currentUser();

        if (! $this->confirmed($user, true)) {
            return redirect()->to(self::URL)->with('error', '비밀번호 또는 인증 코드가 올바르지 않습니다.');
        }

        return redirect()->to(self::URL)
            ->with('success', '복구 코드를 새로 발급했습니다. 이전 코드는 더 쓸 수 없습니다.')
            ->with('recoveryCodes', $this->twoFactor->regenerateRecoveryCodes((int) $user['id']));
    }

    /**
     * 민감 작업 재확인: 인증 코드(필수) + 비밀번호(해제 시, 비밀번호가 있는 계정만).
     *
     * @param array<string, mixed> $user
     */
    private function confirmed(array $user, bool $needPassword): bool
    {
        if (! TwoFactor::isEnabled($user)) {
            return false;
        }

        $code = $this->request->getPost('code');

        if ($needPassword && ! $this->passwordOk($user)) {
            return false;
        }

        return is_string($code) && $this->twoFactor->attempt($user, $code);
    }

    /**
     * 비밀번호가 있는 계정만 확인한다(소셜 전용 계정은 비밀번호가 없어 통과).
     *
     * @param array<string, mixed> $user
     */
    private function passwordOk(array $user): bool
    {
        if (empty($user['password'])) {
            return true;
        }

        $password = $this->request->getPost('password');

        return is_string($password) && password_verify($password, (string) $user['password']);
    }

    private function setupSecret(): ?string
    {
        $stored = session()->getTempdata(self::SETUP_KEY);
        if (! is_string($stored)) {
            return null;
        }

        try {
            return service('encrypter')->decrypt((string) base64_decode($stored, true));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function currentUser(): array
    {
        return $this->userModel->find((int) session()->get('user_id')) ?? [];
    }
}
