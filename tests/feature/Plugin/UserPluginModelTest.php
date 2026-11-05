<?php

declare(strict_types=1);

namespace Tests\Feature\Plugin;

use App\Exceptions\PluginException;
use App\Libraries\Plugin\AccessLevel;
use App\Libraries\Plugin\EntitlementStatus;
use App\Libraries\Plugin\LicenseType;
use App\Models\UserPluginModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class UserPluginModelTest extends DatabaseTestCase
{
    private UserPluginModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new UserPluginModel();
    }

    public function testGrantCreatesEntitlement(): void
    {
        $this->model->grant(7, 'sample', LicenseType::Onetime);

        $entitlement = $this->model->findEntitlement(7, 'sample');

        $this->assertNotNull($entitlement);
        $this->assertSame(LicenseType::Onetime, $entitlement->type);
        $this->assertSame(EntitlementStatus::Active, $entitlement->status);
        $this->assertSame(AccessLevel::Full, $entitlement->accessLevel());
    }

    public function testFindEntitlementReturnsNullWhenAbsent(): void
    {
        $this->assertNull($this->model->findEntitlement(7, 'nope'));
    }

    public function testGrantTwiceUpdatesInsteadOfDuplicating(): void
    {
        $this->model->grant(7, 'sample', LicenseType::Onetime);
        $this->model->grant(7, 'sample', LicenseType::Subscription, '2030-12-31 23:59:59');

        $rows = $this->model->where('user_id', 7)->where('plugin_key', 'sample')->findAll();
        $this->assertCount(1, $rows);

        $entitlement = $this->model->findEntitlement(7, 'sample');
        $this->assertSame(LicenseType::Subscription, $entitlement->type);
        $this->assertSame(strtotime('2030-12-31 23:59:59'), $entitlement->expiresAt);
    }

    public function testGrantRejectsExpiryBeyondTimestampRange(): void
    {
        // MySQL 은 범위를 넘는 TIMESTAMP 를 예외 없이 0000-00-00 으로 자른다.
        // 그러면 "2099년까지 유효"한 구독이 즉시 만료된 것으로 읽힌다.
        $this->expectException(PluginException::class);
        $this->model->grant(7, 'sample', LicenseType::Subscription, '2099-12-31 23:59:59');
    }

    public function testGrantRejectsMalformedExpiry(): void
    {
        $this->expectException(PluginException::class);
        $this->model->grant(7, 'sample', LicenseType::Subscription, '언젠가');
    }

    public function testGrantAfterRevokeReactivates(): void
    {
        $this->model->grant(7, 'sample', LicenseType::Onetime);
        $this->model->revoke(7, 'sample');
        $this->model->grant(7, 'sample', LicenseType::Onetime);

        $entitlement = $this->model->findEntitlement(7, 'sample');
        $this->assertSame(EntitlementStatus::Active, $entitlement->status);
        $this->assertSame(AccessLevel::Full, $entitlement->accessLevel());
    }

    public function testRevokeBlocksAccess(): void
    {
        $this->model->grant(7, 'sample', LicenseType::Onetime);
        $this->model->revoke(7, 'sample');

        $entitlement = $this->model->findEntitlement(7, 'sample');
        $this->assertSame(EntitlementStatus::Revoked, $entitlement->status);
        $this->assertSame(AccessLevel::None, $entitlement->accessLevel());
    }

    public function testCancelSetsCancelledStatusWithoutTouchingExpiry(): void
    {
        $expiresAt = '2030-12-31 23:59:59';
        $this->model->grant(7, 'sample', LicenseType::Subscription, $expiresAt);

        $this->model->cancel(7, 'sample');

        $entitlement = $this->model->findEntitlement(7, 'sample');
        $this->assertSame(EntitlementStatus::Cancelled, $entitlement->status);
        $this->assertSame(strtotime($expiresAt), $entitlement->expiresAt);
        // 만료일까지는 여전히 Full — 해지는 즉시 접근을 막지 않는다
        $this->assertSame(AccessLevel::Full, $entitlement->accessLevel());
    }

    public function testCancelOnMissingEntitlementIsNoOp(): void
    {
        $this->model->cancel(7, 'nope');

        $this->assertNull($this->model->findEntitlement(7, 'nope'));
    }

    public function testExpiredSubscriptionIsReadOnly(): void
    {
        $this->model->grant(7, 'sample', LicenseType::Subscription, '2000-01-01 00:00:00');

        $entitlement = $this->model->findEntitlement(7, 'sample');

        $this->assertSame(AccessLevel::ReadOnly, $entitlement->accessLevel());
    }

    public function testEntitlementsForReturnsOnlyThatUser(): void
    {
        $this->model->grant(7, 'sample', LicenseType::Onetime);
        $this->model->grant(7, 'other', LicenseType::Onetime);
        $this->model->grant(8, 'sample', LicenseType::Onetime);

        $entitlements = $this->model->entitlementsFor(7);

        $this->assertCount(2, $entitlements);

        foreach ($entitlements as $entitlement) {
            $this->assertSame(7, $entitlement->userId);
        }
    }

    public function testHoldersOfJoinsUserInfo(): void
    {
        // 시드된 관리자(id=1)에게 부여
        $this->model->grant(1, 'sample', LicenseType::Onetime);

        $holders = $this->model->holdersOf('sample');

        $this->assertCount(1, $holders);
        $this->assertSame('admin@example.com', $holders[0]['email']);
        $this->assertSame('onetime', $holders[0]['type']);
    }
}
