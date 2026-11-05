<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\PluginException;
use App\Libraries\Plugin\Entitlement;
use App\Libraries\Plugin\EntitlementStatus;
use App\Libraries\Plugin\LicenseType;
use CodeIgniter\Model;

/**
 * 사용자별 플러그인 보유 권한.
 *
 * UserScopedModel 트레이트를 쓰지 않는다 — 관리자가 남의 entitlement 를
 * 다루는 것이 이 모델의 정상 동작이다. 트레이트를 붙이면 관리자 화면이 전부
 * withoutUserScope() 로 감싸여 우회가 기본값처럼 보인다. 사용자 필터는
 * 호출부에서 명시적으로 건다.
 */
class UserPluginModel extends Model
{
    /**
     * expires_at 에 저장할 수 있는 최대 시각.
     *
     * TIMESTAMP 의 상한은 2038-01-19 다. 그 너머를 넣으면 MySQL 이 조용히
     * 0000-00-00 으로 잘라, 만료일이 과거로 읽혀 곧바로 읽기 전용이 된다.
     * 침묵하는 손상보다 명시적 실패가 낫다.
     */
    public const MAX_EXPIRES_AT = '2038-01-18 23:59:59';

    protected $table         = 'user_plugins';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    /**
     * @var list<string>
     */
    protected $allowedFields = [
        'user_id', 'plugin_key', 'type', 'status',
        'purchased_at', 'expires_at', 'cancelled_at',
    ];

    public function findEntitlement(int $userId, string $pluginKey): ?Entitlement
    {
        /** @var array<string, mixed>|null $row */
        $row = $this->where('user_id', $userId)->where('plugin_key', $pluginKey)->first();

        return $row === null ? null : Entitlement::fromRow($row);
    }

    /**
     * @return list<Entitlement>
     */
    public function entitlementsFor(int $userId): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->where('user_id', $userId)->orderBy('plugin_key', 'ASC')->findAll();

        return array_map(static fn (array $row): Entitlement => Entitlement::fromRow($row), $rows);
    }

    /**
     * 부여한다. 이미 있으면 갱신한다 — 사용자·플러그인당 한 행이므로.
     * 회수됐던 것을 다시 부여하면 active 로 되살아난다.
     *
     * @param string|null $expiresAt 'Y-m-d H:i:s' (앱 타임존). 구독에만 쓴다.
     */
    public function grant(int $userId, string $pluginKey, LicenseType $type, ?string $expiresAt = null): void
    {
        $this->assertStorableExpiry($expiresAt);

        /** @var array<string, mixed>|null $existing */
        $existing = $this->where('user_id', $userId)->where('plugin_key', $pluginKey)->first();

        $payload = [
            'type'         => $type->value,
            'status'       => EntitlementStatus::Active->value,
            'expires_at'   => $expiresAt,
            'cancelled_at' => null,
        ];

        if ($existing === null) {
            $this->insert($payload + [
                'user_id'      => $userId,
                'plugin_key'   => $pluginKey,
                'purchased_at' => date('Y-m-d H:i:s'),
            ]);

            return;
        }

        $this->update($existing['id'], $payload);
    }

    /**
     * 회수한다. 행을 지우지 않는 이유: 언제 무엇을 회수했는지가 남아야 하고,
     * 다시 부여할 때 같은 행을 되살리면 되기 때문이다.
     */
    public function revoke(int $userId, string $pluginKey): void
    {
        /** @var array<string, mixed>|null $existing */
        $existing = $this->where('user_id', $userId)->where('plugin_key', $pluginKey)->first();

        if ($existing === null) {
            return;
        }

        $this->update($existing['id'], ['status' => EntitlementStatus::Revoked->value]);
    }

    /**
     * 해지 예약. expires_at 은 건드리지 않는다 — 이미 낸 기간까지는 그대로
     * Full 이고, 지나면 accessLevel() 계산만으로 자동 ReadOnly 가 된다
     * (2단계 원칙: 저장이 아니라 계산 — 설계 §2).
     */
    public function cancel(int $userId, string $pluginKey): void
    {
        /** @var array<string, mixed>|null $existing */
        $existing = $this->where('user_id', $userId)->where('plugin_key', $pluginKey)->first();

        if ($existing === null) {
            return;
        }

        $this->update($existing['id'], [
            'status'       => EntitlementStatus::Cancelled->value,
            'cancelled_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * TIMESTAMP 범위를 벗어나는 만료일을 거부한다.
     *
     * MySQL 은 범위를 넘는 값을 예외 없이 0000-00-00 으로 바꿔 저장한다.
     * 그대로 두면 "2099년까지 유효"하게 부여한 구독이 즉시 만료된 것으로
     * 읽히고, 아무도 그 이유를 모른다.
     */
    private function assertStorableExpiry(?string $expiresAt): void
    {
        if ($expiresAt === null) {
            return;
        }

        $timestamp = strtotime($expiresAt);

        if ($timestamp === false) {
            throw new PluginException("만료일 형식이 올바르지 않습니다: {$expiresAt}");
        }

        if ($timestamp > strtotime(self::MAX_EXPIRES_AT)) {
            throw new PluginException(
                '만료일은 ' . self::MAX_EXPIRES_AT . ' 이전이어야 합니다 (TIMESTAMP 저장 한계).',
            );
        }
    }

    /**
     * 이 플러그인을 보유한 사용자 목록 (관리자 화면용).
     *
     * @return list<array<string, mixed>>
     */
    public function holdersOf(string $pluginKey): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->select('user_plugins.*, users.email, users.nickname')
            ->join('users', 'users.id = user_plugins.user_id', 'inner')
            ->where('user_plugins.plugin_key', $pluginKey)
            ->orderBy('user_plugins.id', 'DESC')
            ->findAll();

        return $rows;
    }
}
