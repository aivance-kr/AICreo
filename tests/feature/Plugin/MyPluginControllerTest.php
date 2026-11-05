<?php

declare(strict_types=1);

namespace Tests\Feature\Plugin;

use App\Libraries\Plugin\EntitlementStatus;
use App\Libraries\Plugin\LicenseType;
use App\Models\PluginModel;
use App\Models\UserPluginModel;
use Config\Services;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class MyPluginControllerTest extends AdminTestCase
{
    protected function tearDown(): void
    {
        // POST 호출이 superglobals·request 서비스에 메서드를 남겨 라우트 수집 테스트(PluginRoutesTest)를 깨뜨린다
        service('superglobals')->setServer('REQUEST_METHOD', 'GET');
        Services::resetSingle('request');
        Services::resetSingle('router');
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedPlugin(string $key, array $overrides = []): void
    {
        (new PluginModel())->insert($overrides + [
            'plugin_key'   => $key,
            'name'         => '샘플 ' . $key,
            'version'      => '1.0.0',
            'status'       => PluginModel::STATUS_ACTIVE,
            'license_type' => LicenseType::Onetime->value,
            'manifest'     => json_encode(['key' => $key], JSON_THROW_ON_ERROR),
        ]);
    }

    public function testGuestIsRedirectedToLogin(): void
    {
        $result = $this->call('get', '/my/plugins');

        $this->assertTrue($result->isRedirect());
        $result->assertRedirectTo('/auth/login');
    }

    public function testMemberSeesActivePluginWithPrice(): void
    {
        $this->seedPlugin('alpha', ['price_onetime' => 49000]);
        $this->seedPlugin('hidden', ['status' => PluginModel::STATUS_INACTIVE]);

        $result = $this->withSession($this->memberSession)->call('get', '/my/plugins');

        $result->assertOK();
        $result->assertSee('샘플 alpha');
        $result->assertSee('49,000');
        $result->assertDontSee('샘플 hidden');
    }

    public function testMemberSeesOwnedBadge(): void
    {
        $this->seedPlugin('alpha');
        (new UserPluginModel())->grant(2, 'alpha', LicenseType::Onetime);

        $result = $this->withSession($this->memberSession)->call('get', '/my/plugins');

        $result->assertSee('보유 중');
    }

    public function testExpiredSubscriptionShowsReadOnlyNotice(): void
    {
        $this->seedPlugin('alpha', ['license_type' => LicenseType::Subscription->value, 'price_subscription' => 9900]);
        (new UserPluginModel())->grant(2, 'alpha', LicenseType::Subscription, '2000-01-01 00:00:00');

        $result = $this->withSession($this->memberSession)->call('get', '/my/plugins');

        $result->assertSee('조회만 가능');
    }

    public function testOtherUsersEntitlementIsNotShown(): void
    {
        $this->seedPlugin('alpha');
        (new UserPluginModel())->grant(3, 'alpha', LicenseType::Onetime);

        $result = $this->withSession($this->memberSession)->call('get', '/my/plugins');

        $result->assertDontSee('보유 중');
        $result->assertSee('미보유');
    }

    public function testCancelSubscriptionKeepsExpiry(): void
    {
        $this->seedPlugin('alpha', ['license_type' => LicenseType::Subscription->value]);
        $model = new UserPluginModel();
        $model->grant(2, 'alpha', LicenseType::Subscription, '2030-12-31 23:59:59');

        $result = $this->withSession($this->memberSession)->call('post', '/my/plugins/alpha/cancel');

        $this->assertTrue($result->isRedirect());
        $entitlement = $model->findEntitlement(2, 'alpha');
        $this->assertSame(EntitlementStatus::Cancelled, $entitlement?->status);
        $this->assertSame(strtotime('2030-12-31 23:59:59'), $entitlement?->expiresAt);
    }

    public function testExpiredSubscriptionCanStillBeCancelled(): void
    {
        $model = new UserPluginModel();
        $model->grant(2, 'alpha', LicenseType::Subscription, '2000-01-01 00:00:00');

        $this->withSession($this->memberSession)->call('post', '/my/plugins/alpha/cancel');

        $this->assertTrue($model->findEntitlement(2, 'alpha')?->isCancelled());
    }

    public function testCancelRejectsOnetime(): void
    {
        $model = new UserPluginModel();
        $model->grant(2, 'alpha', LicenseType::Onetime);

        $this->withSession($this->memberSession)->call('post', '/my/plugins/alpha/cancel');

        $this->assertFalse($model->findEntitlement(2, 'alpha')?->isCancelled());
    }

    public function testCancelCannotTouchOtherUsersSubscription(): void
    {
        $model = new UserPluginModel();
        $model->grant(3, 'alpha', LicenseType::Subscription, '2030-12-31 23:59:59');

        $this->withSession($this->memberSession)->call('post', '/my/plugins/alpha/cancel');

        $this->assertFalse($model->findEntitlement(3, 'alpha')?->isCancelled());
    }

    public function testActivateGrantsCreditPlugin(): void
    {
        $this->seedPlugin('alpha', ['license_type' => LicenseType::Credit->value]);

        $this->withSession($this->memberSession)->call('post', '/my/plugins/alpha/activate');

        $this->assertNotNull((new UserPluginModel())->findEntitlement(2, 'alpha'));
    }

    public function testActivateRejectsPaidPlugin(): void
    {
        $this->seedPlugin('alpha');

        $this->withSession($this->memberSession)->call('post', '/my/plugins/alpha/activate');

        $this->assertNull((new UserPluginModel())->findEntitlement(2, 'alpha'));
    }

    public function testRevokedUserCannotSelfReactivate(): void
    {
        $this->seedPlugin('alpha', ['license_type' => LicenseType::Credit->value]);
        $model = new UserPluginModel();
        $model->grant(2, 'alpha', LicenseType::Credit);
        $model->revoke(2, 'alpha');

        $this->withSession($this->memberSession)->call('post', '/my/plugins/alpha/activate');

        $this->assertSame(EntitlementStatus::Revoked, $model->findEntitlement(2, 'alpha')?->status);
    }

    public function testActivateBlockedWhenRequirementMissing(): void
    {
        $this->seedPlugin('base');
        $this->seedPlugin('addon', [
            'license_type' => LicenseType::Credit->value,
            'manifest'     => json_encode(['key' => 'addon', 'requires' => ['plugins' => ['base']]], JSON_THROW_ON_ERROR),
        ]);

        $this->withSession($this->memberSession)->call('post', '/my/plugins/addon/activate');

        $this->assertNull((new UserPluginModel())->findEntitlement(2, 'addon'));
    }
}
