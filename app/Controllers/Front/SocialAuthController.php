<?php

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Libraries\OAuth\OAuthFactory;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class SocialAuthController extends BaseController
{
    private readonly UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * 소셜 로그인 시작 — 제공자 OAuth 페이지로 리다이렉트
     * GET /auth/social/{provider}
     */
    public function redirect(string $provider): ResponseInterface|string
    {
        if (! in_array($provider, OAuthFactory::supported(), true) || ! $this->isProviderEnabled($provider)) {
            return redirect()->to('/auth/login')->with('error', lang('Auth.socialUnsupported'));
        }

        // CSRF 대용 state 값 생성
        $state = bin2hex(random_bytes(16));
        session()->set('oauth_state', $state);
        session()->set('oauth_provider', $provider);

        $oauth   = OAuthFactory::make($provider);
        $authUrl = $oauth->getAuthUrl($state);

        return redirect()->to($authUrl);
    }

    /**
     * 소셜 로그인 콜백 처리
     * GET /auth/social/{provider}/callback
     */
    public function callback(string $provider): ResponseInterface|string
    {
        if (! in_array($provider, OAuthFactory::supported(), true) || ! $this->isProviderEnabled($provider)) {
            return redirect()->to('/auth/login')->with('error', lang('Auth.socialUnsupported'));
        }

        $code  = $this->request->getGet('code');
        $state = $this->request->getGet('state');
        $error = $this->request->getGet('error');

        // 사용자가 취소
        if ($error) {
            return redirect()->to('/auth/login')->with('error', lang('Auth.socialCancelled'));
        }

        // state 검증 (CSRF 방지)
        if (! $code || $state !== session()->get('oauth_state')) {
            return redirect()->to('/auth/login')->with('error', lang('Auth.badRequest'));
        }

        session()->remove('oauth_state');

        try {
            $oauth = OAuthFactory::make($provider);
            $token = $oauth->getToken($code);

            if (! $token) {
                return redirect()->to('/auth/login')->with('error', lang('Auth.tokenFailed'));
            }

            $profile = $oauth->getProfile($token);

            if (! $profile) {
                return redirect()->to('/auth/login')->with('error', lang('Auth.userInfoFailed'));
            }
        } catch (Throwable $e) {
            log_message('error', '[SocialAuth] ' . $e->getMessage());

            return redirect()->to('/auth/login')->with('error', lang('Auth.socialError'));
        }

        $user = $this->findOrCreateUser($provider, $profile, $token);

        if (! $user) {
            return redirect()->to('/auth/login')->with('error', lang('Auth.accountError'));
        }

        if (! $user['is_active']) {
            return redirect()->to('/auth/login')->with('error', lang('Auth.accountInactive'));
        }

        // 로그인 세션 등록
        return $this->finishLogin($user);
    }

    /**
     * 관리자 설정에서 제공자가 켜져 있는지 확인 (기본값: 사용)
     */
    private function isProviderEnabled(string $provider): bool
    {
        return ($this->viewData['settings']["oauth_{$provider}_enabled"] ?? '1') === '1';
    }

    /**
     * 소셜 ID로 기존 유저 찾기, 없으면 자동 가입
     *
     * @param array<string, mixed> $profile
     *
     * @return array<string, mixed>|null
     */
    private function findOrCreateUser(string $provider, array $profile, string $token): ?array
    {
        // 1. 같은 소셜 제공자 + ID로 기존 가입 확인
        $user = $this->userModel
            ->where('social_provider', $provider)
            ->where('social_id', $profile['social_id'])
            ->first();

        if ($user) {
            // 토큰 및 아바타 갱신
            $this->userModel->update($user['id'], [
                'social_token' => $token,
                'avatar'       => $profile['avatar'],
            ]);

            return $this->userModel->find($user['id']);
        }

        // 2. 같은 이메일로 일반 가입된 계정 있으면 소셜 연동
        if (! empty($profile['email'])) {
            $existing = $this->userModel->where('email', $profile['email'])->first();
            if ($existing) {
                $this->userModel->update($existing['id'], [
                    'social_provider' => $provider,
                    'social_id'       => $profile['social_id'],
                    'social_token'    => $token,
                    'avatar'          => $profile['avatar'],
                ]);

                return $this->userModel->find($existing['id']);
            }
        }

        // 3. 신규 유저 자동 생성
        $nickname = $this->resolveNickname($profile['nickname']);
        $email    = $profile['email'] ?? $provider . '_' . $profile['social_id'] . '@social.local';

        $id = $this->userModel->insert([
            'username'        => $email,
            'email'           => $email,
            'password'        => null,  // 소셜 전용 계정
            'nickname'        => $nickname,
            'role'            => 'member',
            'social_provider' => $provider,
            'social_id'       => $profile['social_id'],
            'social_token'    => $token,
            'avatar'          => $profile['avatar'],
            'is_active'       => 1,
        ]);

        return $this->userModel->find($id);
    }

    /**
     * 닉네임 중복 시 숫자 접미사 추가
     */
    private function resolveNickname(string $nickname): string
    {
        $base    = mb_substr($nickname, 0, 18);
        $attempt = $base;
        $i       = 2;

        while ($this->userModel->where('nickname', $attempt)->countAllResults() > 0) {
            $attempt = $base . $i;
            $i++;
        }

        return $attempt;
    }
}
