<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Passkey;
use App\Libraries\TwoFactor;
use App\Models\AdminLogModel;
use App\Models\PasskeyModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;
use Webauthn\PublicKeyCredentialCreationOptions;

/**
 * 관리자 본인 계정의 2단계 인증(TOTP) 설정·해제·복구 코드 재발급과 패스키 등록·삭제.
 */
class SecurityController extends BaseController
{
    private const URL       = '/admin/security';
    private const SETUP_KEY = 'totp_setup_secret';
    private const SETUP_TTL = 600;

    private readonly UserModel $userModel;
    private readonly TwoFactor $twoFactor;
    private readonly PasskeyModel $passkeys;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->passkeys  = new PasskeyModel();
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
            'passkeys'      => $this->passkeys->listForUser((int) $user['id']),
            'totpEnabled'   => $enabled,
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
     * 패스키 등록 1단계: 재확인을 통과하면 등록용 옵션(챌린지)을 내려준다.
     * 세션을 탈취한 쪽이 자기 패스키를 심어 TOTP 를 우회하지 못하도록 비밀번호(와 켠 경우 TOTP 코드)를 다시 받는다.
     */
    public function passkeyOptions(): ResponseInterface
    {
        $user = $this->currentUser();

        if (! $this->reauthenticated($user)) {
            return $this->response->setStatusCode(403)->setJSON(['error' => '비밀번호 또는 인증 코드가 올바르지 않습니다.']);
        }

        $passkey = new Passkey();
        $options = $passkey->creationOptions($user);
        $passkey->remember(Passkey::CREATION_KEY, $options);

        return $this->response->setJSON([
            'csrf'    => csrf_hash(),
            'options' => json_decode($passkey->toJson($options), true),
        ]);
    }

    /**
     * 패스키 등록 2단계: 기기가 만든 자격 증명을 검증해 저장한다. 본문은 {"name": "...", "credential": {...}}.
     */
    public function passkeyRegister(): ResponseInterface
    {
        $user    = $this->currentUser();
        $body    = $this->request->getJSON(true);
        $passkey = new Passkey();
        $options = $passkey->recall(Passkey::CREATION_KEY, PublicKeyCredentialCreationOptions::class);

        $credential = is_array($body) && is_array($body['credential'] ?? null) ? json_encode($body['credential']) : false;
        $name       = is_array($body) && is_string($body['name'] ?? null) ? $body['name'] : '';
        $row        = $options === null || $credential === false ? null : $passkey->register((int) $user['id'], $name, $credential, $options);

        if ($row === null) {
            return $this->response->setStatusCode(422)->setJSON(['error' => '패스키를 등록하지 못했습니다. 다시 시도해 주세요.']);
        }

        (new AdminLogModel())->record('update', 'user', (int) $user['id'], '패스키 등록');
        session()->setFlashdata('success', '패스키를 등록했습니다.');

        return $this->response->setJSON(['ok' => true]);
    }

    public function passkeyDelete(int $id): ResponseInterface
    {
        $user = $this->currentUser();
        $row  = $this->passkeys->find($id);

        if ($row === null || (int) $row['user_id'] !== (int) $user['id']) {
            return redirect()->to(self::URL)->with('error', '패스키를 찾을 수 없습니다.');
        }

        if (! $this->reauthenticated($user)) {
            return redirect()->to(self::URL)->with('error', '비밀번호 또는 인증 코드가 올바르지 않습니다.');
        }

        $this->passkeys->delete($id);
        (new AdminLogModel())->record('update', 'user', (int) $user['id'], '패스키 삭제');

        return redirect()->to(self::URL)->with('success', '패스키를 삭제했습니다.');
    }

    /**
     * 패스키 추가·삭제 재확인: 비밀번호(있는 계정만) + 2단계 인증을 켠 경우 인증 코드.
     *
     * @param array<string, mixed> $user
     */
    private function reauthenticated(array $user): bool
    {
        if (! $this->passwordOk($user)) {
            return false;
        }

        if (! TwoFactor::isEnabled($user)) {
            return true;
        }

        $code = $this->request->getPost('code');

        return is_string($code) && $this->twoFactor->attempt($user, $code);
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
