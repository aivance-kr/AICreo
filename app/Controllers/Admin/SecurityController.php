<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\TwoFactor;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 관리자 본인 계정의 2단계 인증(TOTP) 설정·해제·복구 코드 재발급.
 */
class SecurityController extends BaseController
{
    private const URL = '/admin/security';

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
            $secret = session()->get('totp_setup_secret');
            if (! is_string($secret)) {
                $secret = $this->twoFactor->generateSecret();
                session()->set('totp_setup_secret', $secret);
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
        $secret = session()->get('totp_setup_secret');
        $code   = $this->request->getPost('code');

        if (TwoFactor::isEnabled($user) || ! TwoFactor::encryptionReady() || ! is_string($secret)) {
            return redirect()->to(self::URL);
        }

        $recovery = is_string($code) ? $this->twoFactor->enable((int) $user['id'], $secret, trim($code)) : null;
        if ($recovery === null) {
            return redirect()->to(self::URL)->with('error', '확인 코드가 올바르지 않습니다. 앱에 표시된 6자리를 입력해 주세요.');
        }

        session()->remove('totp_setup_secret');

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
        session()->remove('totp_setup_secret');

        return redirect()->to(self::URL)->with('success', '2단계 인증을 껐습니다.');
    }

    public function recovery(): ResponseInterface
    {
        $user = $this->currentUser();

        if (! $this->confirmed($user, false)) {
            return redirect()->to(self::URL)->with('error', '인증 코드가 올바르지 않습니다.');
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

        $code     = $this->request->getPost('code');
        $password = $this->request->getPost('password');

        if ($needPassword && ! empty($user['password'])
            && (! is_string($password) || ! password_verify($password, (string) $user['password']))) {
            return false;
        }

        return is_string($code) && $this->twoFactor->attempt($user, $code);
    }

    /**
     * @return array<string, mixed>
     */
    private function currentUser(): array
    {
        return $this->userModel->find((int) session()->get('user_id')) ?? [];
    }
}
