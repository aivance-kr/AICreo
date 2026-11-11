<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Libraries\Passkey;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * 패스키 로그인(관리자 전용). 이메일 입력 없이 기기에 저장된 패스키로 로그인한다.
 *
 * 패스키 자체가 "기기 소지 + 생체/PIN" 이므로 2단계 인증(TOTP)은 거치지 않고 세션을 만든다.
 * 챌린지는 세션에 한 번만 쓸 수 있게 보관된다(Passkey::recall).
 */
class PasskeyController extends BaseController
{
    public function options(): ResponseInterface
    {
        $passkey = new Passkey();
        $options = $passkey->requestOptions();
        $passkey->remember(Passkey::REQUEST_KEY, $options);

        // CSRF 토큰은 요청마다 새로 발급되므로 다음 요청(verify)에 쓸 값을 함께 내려준다
        return $this->response->setJSON([
            'csrf'    => csrf_hash(),
            'options' => json_decode($passkey->toJson($options), true),
        ]);
    }

    public function verify(): ResponseInterface
    {
        $passkey = new Passkey();
        $options = $passkey->recall(Passkey::REQUEST_KEY, PublicKeyCredentialRequestOptions::class);
        $userId  = $options === null ? null : $passkey->authenticate((string) $this->request->getBody(), $options);
        $user    = $userId === null ? null : (new UserModel())->find($userId);

        if ($user === null || ! $user['is_active'] || $user['role'] !== 'admin') {
            return $this->response->setStatusCode(401)->setJSON(['error' => lang('Auth.passkeyFailed')]);
        }

        session()->remove(['twofa_user_id', 'twofa_started']);

        return $this->response->setJSON(['redirect' => $this->openSession($user)]);
    }
}
