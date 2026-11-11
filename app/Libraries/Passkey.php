<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\PasskeyModel;
use App\Models\SettingModel;
use Cose\Algorithms;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * 관리자 패스키(WebAuthn) 등록·로그인 검증.
 *
 * 서명·챌린지·오리진 검증은 web-auth/webauthn-lib 에 맡기고, 여기서는 옵션 생성·세션 보관·저장만 한다.
 * 패스키는 기기 소지 + 생체/PIN(userVerification=required) 이라 TOTP 를 대신한다.
 * 챌린지는 세션에 한 번만 쓸 수 있게 보관한다(꺼내면 지움, 5분 유효).
 */
final class Passkey
{
    public const CREATION_KEY = 'passkey_creation';
    public const REQUEST_KEY  = 'passkey_request';
    private const OPTIONS_TTL = 300;

    private ?SerializerInterface $serializer = null;

    public function __construct(private readonly PasskeyModel $passkeys = new PasskeyModel())
    {
    }

    /**
     * 신뢰 당사자 ID — 사이트 주소(app.baseURL)의 호스트.
     */
    public static function rpId(): string
    {
        return (string) parse_url(config('App')->baseURL, PHP_URL_HOST);
    }

    /**
     * 허용 오리진 — scheme://host[:port].
     */
    public static function origin(): string
    {
        $url  = parse_url(config('App')->baseURL);
        $port = isset($url['port']) ? ':' . $url['port'] : '';

        return ($url['scheme'] ?? 'https') . '://' . ($url['host'] ?? '') . $port;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function creationOptions(array $user): PublicKeyCredentialCreationOptions
    {
        $siteName = (string) ((new SettingModel())->getAllAsMap()['site_name'] ?? 'AICreo');

        // 같은 기기에 같은 패스키를 두 번 등록하지 않도록 이미 등록된 것은 제외
        $exclude = array_map(
            fn (array $row): PublicKeyCredentialDescriptor => $this->record($row)->getPublicKeyCredentialDescriptor(),
            $this->passkeys->listForUser((int) $user['id']),
        );

        return PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create($siteName, self::rpId()),
            PublicKeyCredentialUserEntity::create((string) $user['email'], self::userHandle((int) $user['id']), (string) $user['nickname']),
            random_bytes(32),
            // 서버가 검증할 수 있는 알고리즘(ES256, RS256)을 명시한다 — 비워 두면 브라우저 기본값에 맡기게 된다
            pubKeyCredParams: [
                PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_ES256),
                PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_RS256),
            ],
            excludeCredentials: $exclude,
            // 이메일 입력 없이 로그인할 수 있도록 기기에 저장되는(발견 가능한) 자격 증명을 요구한다
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            ),
            timeout: self::OPTIONS_TTL * 1000,
        );
    }

    public function requestOptions(): PublicKeyCredentialRequestOptions
    {
        return PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            self::rpId(),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: self::OPTIONS_TTL * 1000,
        );
    }

    /**
     * 옵션(챌린지)을 세션에 보관한다. 같은 키로 다시 보관하면 이전 것은 버려진다.
     */
    public function remember(string $key, PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options): void
    {
        session()->setTempdata($key, $this->toJson($options), self::OPTIONS_TTL);
    }

    /**
     * 보관한 옵션을 꺼낸다. 꺼내면 지워서 같은 챌린지를 다시 쓸 수 없다.
     *
     * @template T of PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public function recall(string $key, string $class): ?object
    {
        $json = session()->getTempdata($key);
        session()->removeTempdata($key);

        if (! is_string($json)) {
            return null;
        }

        try {
            return $this->serializer()->deserialize($json, $class, 'json');
        } catch (Throwable) {
            return null;
        }
    }

    public function toJson(object $options): string
    {
        return $this->serializer()->serialize($options, 'json', [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            JsonEncode::OPTIONS                        => JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ]);
    }

    /**
     * 등록 응답을 검증해 저장한다. 실패하면 null.
     *
     * @return array<string, mixed>|null 저장된 행
     */
    public function register(int $userId, string $name, string $credentialJson, PublicKeyCredentialCreationOptions $options): ?array
    {
        try {
            $credential = $this->serializer()->deserialize($credentialJson, PublicKeyCredential::class, 'json');
            $response   = $credential->response;
            if (! $response instanceof AuthenticatorAttestationResponse) {
                return null;
            }

            $record = AuthenticatorAttestationResponseValidator::create($this->ceremony()->creationCeremony())
                ->check($response, $options, self::rpId());
        } catch (Throwable $e) {
            log_message('warning', '[Passkey] 등록 검증 실패: ' . $e::class);

            return null;
        }

        $hash = PasskeyModel::hash($record->publicKeyCredentialId);
        if ($this->passkeys->where('credential_hash', $hash)->first() !== null) {
            return null;
        }

        $id = $this->passkeys->insert([
            'user_id'         => $userId,
            'name'            => mb_substr(trim($name), 0, 100) ?: '패스키',
            'credential_hash' => $hash,
            'credential'      => $this->serializer()->serialize($record, 'json'),
        ]);

        return $this->passkeys->find((int) $id);
    }

    /**
     * 로그인 응답을 검증한다. 성공하면 패스키가 속한 사용자 ID, 실패하면 null.
     * 서명 횟수·마지막 사용 시각도 갱신한다. 사용자 상태(활성·관리자)는 호출한 쪽에서 확인한다.
     */
    public function authenticate(string $credentialJson, PublicKeyCredentialRequestOptions $options): ?int
    {
        try {
            $credential = $this->serializer()->deserialize($credentialJson, PublicKeyCredential::class, 'json');
            $response   = $credential->response;
            if (! $response instanceof AuthenticatorAssertionResponse) {
                return null;
            }

            $row = $this->passkeys->findByCredentialId($credential->rawId);
            if ($row === null) {
                return null;
            }

            $updated = AuthenticatorAssertionResponseValidator::create($this->ceremony()->requestCeremony())
                ->check($this->record($row), $response, $options, self::rpId(), $response->userHandle);
        } catch (Throwable $e) {
            log_message('warning', '[Passkey] 로그인 검증 실패: ' . $e::class);

            return null;
        }

        $this->passkeys->update((int) $row['id'], [
            'credential'   => $this->serializer()->serialize($updated, 'json'),
            'last_used_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $row['user_id'];
    }

    /**
     * 사용자별로 고정된, 개인 정보가 아닌 식별자(WebAuthn user.id).
     */
    private static function userHandle(int $userId): string
    {
        return hash_hmac('sha256', (string) $userId, self::rpId(), true);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function record(array $row): CredentialRecord
    {
        return $this->serializer()->deserialize((string) $row['credential'], CredentialRecord::class, 'json');
    }

    private function ceremony(): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins([self::origin()]);

        return $factory;
    }

    private function serializer(): SerializerInterface
    {
        return $this->serializer ??= (new WebauthnSerializerFactory(
            new AttestationStatementSupportManager([new NoneAttestationStatementSupport()]),
        ))->create();
    }
}
