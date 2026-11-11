<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Passkey;
use App\Libraries\TwoFactor;
use App\Models\PasskeyModel;
use App\Models\UserModel;
use OpenSSLAsymmetricKey;
use OTPHP\TOTP;
use Tests\Support\AdminTestCase;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * 관리자 패스키(WebAuthn) 등록·로그인 (#567).
 *
 * 실제 인증기 대신 테스트가 ES256 키로 등록·서명 응답을 직접 만든다.
 * 검증 자체(서명·챌린지·오리진·서명 횟수)는 web-auth/webauthn-lib 가 진짜로 수행한다.
 *
 * @internal
 */
final class PasskeyLoginTest extends AdminTestCase
{
    private const ORIGIN = 'http://localhost:8080';
    private const RP_ID  = 'localhost';

    private string $secret;
    private OpenSSLAsymmetricKey $key;
    private string $rawId;

    /**
     * 등록 시 옵션에서 정해진 user.id(= 로그인 응답의 userHandle)
     */
    private string $userHandle;

    protected function setUp(): void
    {
        parent::setUp();

        // 패스키로 로그인하면 TOTP 를 건너뛰는지 보려고 관리자는 2단계 인증을 켜 둔다
        $twoFactor    = new TwoFactor();
        $this->secret = $twoFactor->generateSecret();
        $twoFactor->enable(1, $this->secret, TOTP::createFromSecret($this->secret)->now());

        $this->key   = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->rawId = random_bytes(32);

        $this->registerPasskey(1, '테스트 키');
    }

    // ─── 로그인 ───────────────────────────────────────────────────────────────

    public function testOptionsEndpointIssuesChallengeForDiscoverableLogin(): void
    {
        $result = $this->post('auth/passkey/options');

        $result->assertStatus(200);
        $data = json_decode($result->getJSON(), true);
        $this->assertNotSame('', $data['options']['challenge']);
        $this->assertSame(self::RP_ID, $data['options']['rpId']);
        $this->assertSame('required', $data['options']['userVerification']);
        $this->assertEmpty($data['options']['allowCredentials'] ?? []);
        $this->assertArrayHasKey('csrf', $data);
    }

    public function testValidAssertionLogsInAndSkipsTotp(): void
    {
        $session = $this->requestSession($options = $this->requestOptions());

        $result = $this->withSession($session)->withBody($this->assertion($options, 1))->post('auth/passkey/verify');

        $result->assertStatus(200);
        $this->assertSame('/', json_decode($result->getJSON(), true)['redirect']);
        $this->assertSame(1, (int) session()->get('user_id'));
        $this->assertSame('admin', session()->get('user_role'));
        $this->assertNull(session()->get('twofa_user_id'));
        $this->assertNotNull((new PasskeyModel())->findByCredentialId($this->rawId)['last_used_at']);
    }

    public function testAssertionAfterLoginRaisesStoredCounter(): void
    {
        $this->withSession($this->requestSession($options = $this->requestOptions()))
            ->withBody($this->assertion($options, 5))->post('auth/passkey/verify')->assertStatus(200);

        // 같은(또는 더 낮은) 서명 횟수의 응답은 복제된 인증기로 보고 거절한다
        $this->withSession($this->requestSession($options = $this->requestOptions()))
            ->withBody($this->assertion($options, 5))->post('auth/passkey/verify')->assertStatus(401);
    }

    public function testTamperedSignatureIsRejected(): void
    {
        $options                       = $this->requestOptions();
        $json                          = json_decode($this->assertion($options, 1), true);
        $sig                           = $this->unb64($json['response']['signature']);
        $json['response']['signature'] = $this->b64(substr($sig, 0, -1) . chr(ord($sig[-1]) ^ 1));

        $this->withSession($this->requestSession($options))->withBody(json_encode($json))->post('auth/passkey/verify')
            ->assertStatus(401);
        $this->assertNull(session()->get('user_id'));
    }

    public function testResponseForAnotherChallengeIsRejected(): void
    {
        $signedFor = $this->requestOptions();
        $stored    = $this->requestOptions();

        $this->withSession($this->requestSession($stored))->withBody($this->assertion($signedFor, 1))
            ->post('auth/passkey/verify')->assertStatus(401);
    }

    public function testResponseFromAnotherOriginIsRejected(): void
    {
        $this->withSession($this->requestSession($options = $this->requestOptions()))
            ->withBody($this->assertion($options, 1, 'https://evil.example'))->post('auth/passkey/verify')
            ->assertStatus(401);
        $this->assertNull(session()->get('user_id'));
    }

    public function testVerifyWithoutIssuedChallengeIsRejected(): void
    {
        $this->withBody($this->assertion($this->requestOptions(), 1))->post('auth/passkey/verify')->assertStatus(401);
    }

    public function testUnknownPasskeyIsRejected(): void
    {
        (new PasskeyModel())->where('user_id', 1)->delete();

        $this->withSession($this->requestSession($options = $this->requestOptions()))
            ->withBody($this->assertion($options, 1))->post('auth/passkey/verify')->assertStatus(401);
    }

    public function testChallengeCanOnlyBeRecalledOnce(): void
    {
        $passkey = new Passkey();
        $passkey->remember(Passkey::REQUEST_KEY, $this->requestOptions());

        $this->assertNotNull($passkey->recall(Passkey::REQUEST_KEY, PublicKeyCredentialRequestOptions::class));
        $this->assertNull($passkey->recall(Passkey::REQUEST_KEY, PublicKeyCredentialRequestOptions::class));
    }

    public function testNonAdminPasskeyDoesNotLogIn(): void
    {
        (new UserModel())->update(1, ['role' => 'member']);

        $this->withSession($this->requestSession($options = $this->requestOptions()))
            ->withBody($this->assertion($options, 1))->post('auth/passkey/verify')->assertStatus(401);
        $this->assertNull(session()->get('user_id'));
    }

    public function testInactiveAdminPasskeyDoesNotLogIn(): void
    {
        (new UserModel())->update(1, ['is_active' => 0]);

        $this->withSession($this->requestSession($options = $this->requestOptions()))
            ->withBody($this->assertion($options, 1))->post('auth/passkey/verify')->assertStatus(401);
        $this->assertNull(session()->get('user_id'));
    }

    // ─── 등록·삭제 ────────────────────────────────────────────────────────────

    public function testRegisterOptionsNeedPassword(): void
    {
        $this->withSession($this->adminSession)->post('admin/security/passkeys/options', ['password' => 'wrong', 'code' => $this->code()])
            ->assertStatus(403);
    }

    public function testRegisterOptionsNeedTotpCodeWhenEnabled(): void
    {
        $this->withSession($this->adminSession)->post('admin/security/passkeys/options', ['password' => 'admin1234!'])
            ->assertStatus(403);
    }

    public function testRegisterOptionsRequireResidentKeyAndExcludeExisting(): void
    {
        $result = $this->withSession($this->adminSession)
            ->post('admin/security/passkeys/options', ['password' => 'admin1234!', 'code' => $this->code()]);

        $result->assertStatus(200);
        $options = json_decode($result->getJSON(), true)['options'];
        $this->assertSame('required', $options['authenticatorSelection']['residentKey']);
        $this->assertSame('required', $options['authenticatorSelection']['userVerification']);
        $this->assertCount(1, $options['excludeCredentials']);
        $this->assertSame([-7, -257], array_column($options['pubKeyCredParams'], 'alg'));
        $this->assertSame(self::RP_ID, $options['rp']['id']);
    }

    public function testRegisterStoresVerifiedCredential(): void
    {
        $this->rawId = random_bytes(32);
        $options     = (new Passkey())->creationOptions((new UserModel())->find(1));
        $body        = json_encode(['name' => '새 기기', 'credential' => json_decode($this->attestation($options), true)]);

        $this->withSession($this->creationSession($options))->withBody($body)->post('admin/security/passkeys')
            ->assertStatus(200);

        $row = (new PasskeyModel())->findByCredentialId($this->rawId);
        $this->assertSame('새 기기', $row['name']);
        $this->assertSame(1, (int) $row['user_id']);
    }

    public function testRegisterRejectsResponseForAnotherChallenge(): void
    {
        $this->rawId = random_bytes(32);
        $stored      = (new Passkey())->creationOptions((new UserModel())->find(1));
        $other       = (new Passkey())->creationOptions((new UserModel())->find(1));
        $body        = json_encode(['name' => 'x', 'credential' => json_decode($this->attestation($other), true)]);

        $this->withSession($this->creationSession($stored))->withBody($body)->post('admin/security/passkeys')
            ->assertStatus(422);
        $this->assertNull((new PasskeyModel())->findByCredentialId($this->rawId));
    }

    public function testDeleteNeedsPasswordAndCode(): void
    {
        $id = (int) (new PasskeyModel())->findByCredentialId($this->rawId)['id'];

        $this->withSession($this->adminSession)->post("admin/security/passkeys/{$id}/delete", ['password' => 'wrong', 'code' => $this->code()])
            ->assertRedirectTo('/admin/security');
        $this->assertNotNull((new PasskeyModel())->find($id));

        $this->withSession($this->adminSession)->post("admin/security/passkeys/{$id}/delete", ['password' => 'admin1234!', 'code' => $this->code()])
            ->assertRedirectTo('/admin/security');
        $this->assertNull((new PasskeyModel())->find($id));
    }

    public function testCannotDeleteAnotherUsersPasskey(): void
    {
        $other = (int) (new UserModel())->insert([
            'username' => 'other@example.com', 'email' => 'other@example.com', 'nickname' => '다른관리자',
            'password' => password_hash('other1234!', PASSWORD_DEFAULT), 'role' => 'admin', 'is_active' => 1,
        ]);
        $this->rawId = random_bytes(32);
        $this->registerPasskey($other, '남의 키');
        $id = (int) (new PasskeyModel())->findByCredentialId($this->rawId)['id'];

        $this->withSession($this->adminSession)->post("admin/security/passkeys/{$id}/delete", ['password' => 'admin1234!', 'code' => $this->code()])
            ->assertRedirectTo('/admin/security');

        $this->assertNotNull((new PasskeyModel())->find($id));
    }

    public function testSecurityPageListsPasskeys(): void
    {
        $result = $this->withSession($this->adminSession)->get('admin/security');

        $result->assertStatus(200);
        $this->assertStringContainsString('테스트 키', (string) $result->response()->getBody());
    }

    public function testLoginPageOffersPasskeyButton(): void
    {
        $this->assertStringContainsString('passkey-login-btn', (string) $this->get('auth/login')->getBody());
    }

    // ─── 헬퍼 ────────────────────────────────────────────────────────────────

    private function code(): string
    {
        return TOTP::createFromSecret($this->secret)->at(time() + 30);
    }

    private function requestOptions(): PublicKeyCredentialRequestOptions
    {
        return (new Passkey())->requestOptions();
    }

    /**
     * @return array<string, mixed> 로그인용 챌린지가 세션에 보관된 상태(5분 유효)
     */
    private function requestSession(PublicKeyCredentialRequestOptions $options): array
    {
        return [Passkey::REQUEST_KEY => (new Passkey())->toJson($options), '__ci_vars' => [Passkey::REQUEST_KEY => time() + 300]];
    }

    /**
     * @return array<string, mixed>
     */
    private function creationSession(PublicKeyCredentialCreationOptions $options): array
    {
        return $this->adminSession + [Passkey::CREATION_KEY => (new Passkey())->toJson($options), '__ci_vars' => [Passkey::CREATION_KEY => time() + 300]];
    }

    /**
     * 테스트 키로 만든 등록 응답을 라이브러리에 통과시켜 저장한다(등록 경로 검증 겸용).
     */
    private function registerPasskey(int $userId, string $name): void
    {
        $user    = (new UserModel())->find($userId);
        $options = (new Passkey())->creationOptions($user);
        $row     = (new Passkey())->register($userId, $name, $this->attestation($options), $options);

        $this->assertNotNull($row, '테스트 패스키 등록이 검증을 통과해야 한다');
        $this->userHandle = $options->user->id;
    }

    private function attestation(PublicKeyCredentialCreationOptions $options): string
    {
        $authData = hash('sha256', self::RP_ID, true)
            . chr(0x45)                 // 사용자 존재(UP) + 사용자 확인(UV) + 자격 증명 데이터(AT)
            . pack('N', 0)
            . str_repeat("\0", 16)      // AAGUID
            . pack('n', strlen($this->rawId)) . $this->rawId
            . $this->cosePublicKey();

        $attestationObject = "\xA3"
            . $this->cborText('fmt') . $this->cborText('none')
            . $this->cborText('attStmt') . "\xA0"
            . $this->cborText('authData') . $this->cborBytes($authData);

        return json_encode([
            'id'       => $this->b64($this->rawId),
            'rawId'    => $this->b64($this->rawId),
            'type'     => 'public-key',
            'response' => [
                'clientDataJSON'    => $this->b64($this->clientData('webauthn.create', $options->challenge, self::ORIGIN)),
                'attestationObject' => $this->b64($attestationObject),
                'transports'        => ['internal'],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function assertion(PublicKeyCredentialRequestOptions $options, int $counter, string $origin = self::ORIGIN): string
    {
        $clientData = $this->clientData('webauthn.get', $options->challenge, $origin);
        $authData   = hash('sha256', self::RP_ID, true) . chr(0x05) . pack('N', $counter);   // UP + UV
        openssl_sign($authData . hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return json_encode([
            'id'       => $this->b64($this->rawId),
            'rawId'    => $this->b64($this->rawId),
            'type'     => 'public-key',
            'response' => [
                'clientDataJSON'    => $this->b64($clientData),
                'authenticatorData' => $this->b64($authData),
                'signature'         => $this->b64($signature),
                'userHandle'        => $this->b64($this->userHandle),
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function clientData(string $type, string $challenge, string $origin): string
    {
        return json_encode(['type' => $type, 'challenge' => $this->b64($challenge), 'origin' => $origin, 'crossOrigin' => false], JSON_THROW_ON_ERROR);
    }

    /**
     * COSE_Key(EC2, ES256, P-256)를 CBOR 로 직접 인코딩한다.
     */
    private function cosePublicKey(): string
    {
        $ec = openssl_pkey_get_details($this->key)['ec'];

        return "\xA5\x01\x02\x03\x26\x20\x01"
            . "\x21" . $this->cborBytes(str_pad($ec['x'], 32, "\0", STR_PAD_LEFT))
            . "\x22" . $this->cborBytes(str_pad($ec['y'], 32, "\0", STR_PAD_LEFT));
    }

    private function cborBytes(string $bytes): string
    {
        $length = strlen($bytes);

        return match (true) {
            $length < 24  => chr(0x40 + $length) . $bytes,
            $length < 256 => "\x58" . chr($length) . $bytes,
            default       => "\x59" . pack('n', $length) . $bytes,
        };
    }

    private function cborText(string $text): string
    {
        return chr(0x60 + strlen($text)) . $text;
    }

    private function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function unb64(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/'), true);
    }
}
