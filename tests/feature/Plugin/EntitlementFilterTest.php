<?php

declare(strict_types=1);

namespace Tests\Feature\Plugin;

use App\Libraries\Plugin\LicenseType;
use App\Models\UserPluginModel;
use CodeIgniter\Test\TestResponse;
use Tests\Support\FeatureTestCase;

/**
 * 필터만 검증한다 — 실제 플러그인 라우트가 아니라 임시 라우트를 쓴다.
 *
 * @internal
 */
final class EntitlementFilterTest extends FeatureTestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $memberSession = [
        'user_id'       => 2,
        'user_nickname' => '회원',
        'user_role'     => 'member',
    ];

    /**
     * @param array<string, mixed>|null $session
     */
    private function callProbe(string $method, ?array $session = null): TestResponse
    {
        $test = $this->withRoutes([
            [
                $method,
                'probe/sample',
                '\Tests\Support\Controllers\ProbeController::index',
                ['filter' => 'entitlement:sample'],
            ],
        ]);

        if ($session !== null) {
            $test = $test->withSession($session);
        }

        return $test->call($method, 'probe/sample');
    }

    public function testGuestIsRedirectedToLogin(): void
    {
        $result = $this->callProbe('get');

        $this->assertTrue($result->isRedirect());
        $result->assertRedirectTo('/auth/login');
    }

    public function testMemberWithoutEntitlementIsRedirectedToStore(): void
    {
        $result = $this->callProbe('get', $this->memberSession);

        $this->assertTrue($result->isRedirect());
        $result->assertRedirectTo('/my/plugins');
    }

    public function testHolderPassesRead(): void
    {
        (new UserPluginModel())->grant(2, 'sample', LicenseType::Onetime);

        $result = $this->callProbe('get', $this->memberSession);

        $result->assertOK();
        $result->assertSee('PROBE');
    }

    public function testHolderPassesWrite(): void
    {
        (new UserPluginModel())->grant(2, 'sample', LicenseType::Onetime);

        $result = $this->callProbe('post', $this->memberSession);

        $result->assertOK();
    }

    public function testExpiredSubscriptionPassesRead(): void
    {
        (new UserPluginModel())->grant(2, 'sample', LicenseType::Subscription, '2000-01-01 00:00:00');

        $result = $this->callProbe('get', $this->memberSession);

        $result->assertOK();
    }

    public function testExpiredSubscriptionIsBlockedOnWrite(): void
    {
        (new UserPluginModel())->grant(2, 'sample', LicenseType::Subscription, '2000-01-01 00:00:00');

        $result = $this->callProbe('post', $this->memberSession);

        $this->assertTrue($result->isRedirect());
        $result->assertRedirectTo('/my/plugins');
    }

    public function testRevokedIsBlockedOnRead(): void
    {
        $model = new UserPluginModel();
        $model->grant(2, 'sample', LicenseType::Onetime);
        $model->revoke(2, 'sample');

        $result = $this->callProbe('get', $this->memberSession);

        $this->assertTrue($result->isRedirect());
        $result->assertRedirectTo('/my/plugins');
    }
}
