<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Libraries\TwoFactor;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 로그인 2단계: 비밀번호·소셜 인증을 통과한 관리자가 TOTP/복구 코드를 입력하면 세션이 완성된다.
 */
class TwoFactorController extends BaseController
{
    // 코드 입력 대기 시간(초)
    private const PENDING_TTL = 300;

    public function form(): ResponseInterface|string
    {
        $user = $this->pendingUser();
        if ($user === null) {
            return redirect()->to('/auth/login');
        }

        return $this->render('auth/two_factor', [
            'page'   => ['title' => '2단계 인증', 'noindex' => true],
            'locked' => TwoFactor::isLocked($user),
        ]);
    }

    public function verify(): ResponseInterface|string
    {
        $user = $this->pendingUser();
        if ($user === null) {
            return redirect()->to('/auth/login')->with('error', '인증 시간이 지났습니다. 다시 로그인해 주세요.');
        }

        if (TwoFactor::isLocked($user)) {
            return redirect()->to('/auth/2fa');
        }

        $code = $this->request->getPost('code');

        if (! is_string($code) || ! (new TwoFactor())->attempt($user, $code)) {
            $locked = TwoFactor::isLocked((new UserModel())->find((int) $user['id']) ?? []);
            if ($locked) {
                session()->remove(['twofa_user_id', 'twofa_started']);
            }

            return $locked
                ? redirect()->to('/auth/login')->with('error', '인증에 여러 번 실패해 ' . TwoFactor::LOCK_MINUTES . '분간 잠겼습니다.')
                : redirect()->to('/auth/2fa')->with('error', '코드가 올바르지 않습니다.');
        }

        session()->remove(['twofa_user_id', 'twofa_started']);

        return $this->startSession($user);
    }

    /**
     * 대기 중인 관리자. 만료·비활성·2FA 해제면 대기 상태를 지우고 null.
     *
     * @return array<string, mixed>|null
     */
    private function pendingUser(): ?array
    {
        $id      = (int) session()->get('twofa_user_id');
        $started = (int) session()->get('twofa_started');

        if ($id === 0 || time() - $started > self::PENDING_TTL) {
            session()->remove(['twofa_user_id', 'twofa_started']);

            return null;
        }

        $user = (new UserModel())->find($id);
        if ($user === null || ! $user['is_active'] || $user['role'] !== 'admin' || ! TwoFactor::isEnabled($user)) {
            session()->remove(['twofa_user_id', 'twofa_started']);

            return null;
        }

        return $user;
    }
}
