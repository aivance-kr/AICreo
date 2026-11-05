<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin;

use App\Libraries\Plugin\AccessLevel;
use App\Libraries\Plugin\Entitlement;
use App\Libraries\Plugin\EntitlementStatus;
use App\Libraries\Plugin\LicenseType;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EntitlementTest extends CIUnitTestCase
{
    private const NOW = 1_800_000_000; // 고정 기준 시각

    private function make(
        LicenseType $type = LicenseType::Subscription,
        EntitlementStatus $status = EntitlementStatus::Active,
        ?int $expiresAt = null,
    ): Entitlement {
        return new Entitlement(
            userId: 7,
            pluginKey: 'sample',
            type: $type,
            status: $status,
            expiresAt: $expiresAt,
        );
    }

    public function testRevokedIsAlwaysNone(): void
    {
        $entitlement = $this->make(
            LicenseType::Onetime,
            EntitlementStatus::Revoked,
            self::NOW + 86400,
        );

        $this->assertSame(AccessLevel::None, $entitlement->accessLevel(self::NOW));
    }

    public function testOnetimeIsFullEvenWithPastExpiry(): void
    {
        $entitlement = $this->make(LicenseType::Onetime, expiresAt: self::NOW - 86400);

        $this->assertSame(AccessLevel::Full, $entitlement->accessLevel(self::NOW));
    }

    public function testCreditIsFull(): void
    {
        $entitlement = $this->make(LicenseType::Credit);

        $this->assertSame(AccessLevel::Full, $entitlement->accessLevel(self::NOW));
    }

    public function testSubscriptionBeforeExpiryIsFull(): void
    {
        $entitlement = $this->make(expiresAt: self::NOW + 1);

        $this->assertSame(AccessLevel::Full, $entitlement->accessLevel(self::NOW));
    }

    public function testSubscriptionAfterExpiryIsReadOnly(): void
    {
        $entitlement = $this->make(expiresAt: self::NOW - 1);

        $this->assertSame(AccessLevel::ReadOnly, $entitlement->accessLevel(self::NOW));
    }

    public function testSubscriptionAtExactExpiryIsStillFull(): void
    {
        // 경계는 포함 — 만료 "시각까지" 쓸 수 있다
        $entitlement = $this->make(expiresAt: self::NOW);

        $this->assertSame(AccessLevel::Full, $entitlement->accessLevel(self::NOW));
    }

    public function testSubscriptionWithoutExpiryIsReadOnly(): void
    {
        // 만료일 없는 구독은 데이터 결함이다. 결함일 때 열어주는 쪽으로 기울지 않는다.
        $entitlement = $this->make(expiresAt: null);

        $this->assertSame(AccessLevel::ReadOnly, $entitlement->accessLevel(self::NOW));
    }

    public function testCancelledBeforeExpiryIsStillFull(): void
    {
        $entitlement = $this->make(
            status: EntitlementStatus::Cancelled,
            expiresAt: self::NOW + 86400,
        );

        $this->assertSame(AccessLevel::Full, $entitlement->accessLevel(self::NOW));
    }

    public function testCancelledAfterExpiryIsReadOnly(): void
    {
        $entitlement = $this->make(
            status: EntitlementStatus::Cancelled,
            expiresAt: self::NOW - 86400,
        );

        $this->assertSame(AccessLevel::ReadOnly, $entitlement->accessLevel(self::NOW));
    }

    public function testCanWrite(): void
    {
        $this->assertTrue(AccessLevel::Full->canWrite());
        $this->assertFalse(AccessLevel::ReadOnly->canWrite());
        $this->assertFalse(AccessLevel::None->canWrite());
    }

    public function testFromRowParsesDatabaseShape(): void
    {
        $entitlement = Entitlement::fromRow([
            'user_id'      => '7',
            'plugin_key'   => 'sample',
            'type'         => 'subscription',
            'status'       => 'active',
            'expires_at'   => '2026-12-31 23:59:59',
            'purchased_at' => '2026-01-01 00:00:00',
            'cancelled_at' => null,
        ]);

        $this->assertSame(7, $entitlement->userId);
        $this->assertSame('sample', $entitlement->pluginKey);
        $this->assertSame(LicenseType::Subscription, $entitlement->type);
        $this->assertSame(EntitlementStatus::Active, $entitlement->status);
        $this->assertSame(strtotime('2026-12-31 23:59:59'), $entitlement->expiresAt);
        $this->assertNull($entitlement->cancelledAt);
    }

    public function testIsExpiredAndIsCancelled(): void
    {
        $expired = $this->make(expiresAt: self::NOW - 1);
        $this->assertTrue($expired->isExpired(self::NOW));

        $live = $this->make(expiresAt: self::NOW + 1);
        $this->assertFalse($live->isExpired(self::NOW));

        // onetime 은 만료 개념이 없다
        $onetime = $this->make(LicenseType::Onetime, expiresAt: self::NOW - 1);
        $this->assertFalse($onetime->isExpired(self::NOW));

        $cancelled = $this->make(status: EntitlementStatus::Cancelled);
        $this->assertTrue($cancelled->isCancelled());
    }
}
