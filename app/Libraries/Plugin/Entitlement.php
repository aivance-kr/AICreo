<?php

declare(strict_types=1);

namespace App\Libraries\Plugin;

/**
 * 한 사용자의 한 플러그인 보유 정보.
 *
 * 접근 판정(accessLevel)이 존재하는 유일한 장소다. 필터도 화면도 여기에
 * 물어보기만 하고 스스로 판정하지 않는다.
 */
final readonly class Entitlement
{
    public function __construct(
        public int $userId,
        public string $pluginKey,
        public LicenseType $type,
        public EntitlementStatus $status,
        public ?int $expiresAt = null,
        public ?int $purchasedAt = null,
        public ?int $cancelledAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row user_plugins 한 행
     */
    public static function fromRow(array $row): self
    {
        return new self(
            userId: (int) ($row['user_id'] ?? 0),
            pluginKey: (string) ($row['plugin_key'] ?? ''),
            type: LicenseType::from((string) ($row['type'] ?? '')),
            status: EntitlementStatus::from((string) ($row['status'] ?? '')),
            expiresAt: self::toTimestamp($row['expires_at'] ?? null),
            purchasedAt: self::toTimestamp($row['purchased_at'] ?? null),
            cancelledAt: self::toTimestamp($row['cancelled_at'] ?? null),
        );
    }

    /**
     * 접근 수준을 계산한다.
     *
     * 만료를 DB status 에 저장하지 않는 이유: 저장하면 갱신 크론이 돌기 전까지
     * 실제와 어긋나고, 접근 제어가 배치 지연에 의존하게 된다.
     *
     * @param int|null $now 기준 시각(유닉스). 테스트에서 만료 경계를 고정하려고 받는다.
     */
    public function accessLevel(?int $now = null): AccessLevel
    {
        if ($this->status === EntitlementStatus::Revoked) {
            return AccessLevel::None;
        }

        // 일회성·크레딧은 만료 개념이 없다 (크레딧 잔액 검사는 6단계)
        if ($this->type !== LicenseType::Subscription) {
            return AccessLevel::Full;
        }

        // 만료일 없는 구독은 데이터 결함이다 — 결함일 때 열어주지 않는다
        if ($this->expiresAt === null) {
            return AccessLevel::ReadOnly;
        }

        // 경계는 포함 — 만료 "시각까지" 쓸 수 있다
        return $this->expiresAt >= ($now ?? time())
            ? AccessLevel::Full
            : AccessLevel::ReadOnly;
    }

    public function isExpired(?int $now = null): bool
    {
        if ($this->type !== LicenseType::Subscription || $this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < ($now ?? time());
    }

    public function isCancelled(): bool
    {
        return $this->status === EntitlementStatus::Cancelled;
    }

    private static function toTimestamp(mixed $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : $timestamp;
    }
}
