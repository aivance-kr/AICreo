<?php

declare(strict_types=1);

namespace App\Controllers\My;

use App\Controllers\BaseController;
use App\Libraries\Plugin\EntitlementStatus;
use App\Libraries\Plugin\LicenseType;
use App\Libraries\Plugin\PluginDependencyService;
use App\Models\PluginModel;
use App\Models\UserPluginModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 회원의 플러그인 목록·보유 현황.
 *
 * entitlement 필터를 걸지 않는다 — 미보유 사용자가 EntitlementFilter 에
 * 걸려 안내받아 도착하는 곳이다. 여기에 걸면 무한 리다이렉트가 된다.
 * (auth:member 는 라우트에서 건다.)
 */
class PluginController extends BaseController
{
    public function index(): string
    {
        $userId = (int) session()->get('user_id');

        /** @var list<array<string, mixed>> $plugins */
        $plugins = (new PluginModel())
            ->where('status', PluginModel::STATUS_ACTIVE)
            ->orderBy('plugin_key', 'ASC')
            ->findAll();

        $entitlements = [];

        foreach ((new UserPluginModel())->entitlementsFor($userId) as $entitlement) {
            $entitlements[$entitlement->pluginKey] = $entitlement;
        }

        $dependencies        = new PluginDependencyService();
        $missingRequirements = [];

        foreach ($plugins as $plugin) {
            $missingRequirements[$plugin['plugin_key']] = $dependencies->missingForUser($userId, $plugin);
        }

        return $this->render('my/plugins/index', [
            'pageTitle'           => '플러그인',
            'plugins'             => $plugins,
            'entitlements'        => $entitlements,
            'missingRequirements' => $missingRequirements,
        ]);
    }

    /**
     * 구독 해지 예약.
     *
     * 만료돼 읽기 전용이 된 구독도 해지할 수 있어야 한다 — cancel() 은
     * status·cancelled_at 만 바꿀 뿐 expires_at 을 건드리지 않아 어떤 접근
     * 수준에서도 파괴적이지 않다. 그래서 Full 접근을 요구하지 않고, 이미
     * 해지·회수된 것만 거른다.
     */
    public function cancelSubscription(string $key): ResponseInterface
    {
        $userId      = (int) session()->get('user_id');
        $model       = new UserPluginModel();
        $entitlement = $model->findEntitlement($userId, $key);

        if (
            $entitlement === null
            || $entitlement->type !== LicenseType::Subscription
            || $entitlement->isCancelled()
            || $entitlement->status === EntitlementStatus::Revoked
        ) {
            return redirect()->to('/my/plugins')->with('error', '해지할 구독을 찾을 수 없습니다.');
        }

        $model->cancel($userId, $key);

        return redirect()->to('/my/plugins')->with('success', '구독 해지를 예약했습니다. 만료일까지는 계속 이용할 수 있습니다.');
    }

    /**
     * 크레딧 라이선스 플러그인 무료 활성화. 결제를 거치지 않는다.
     *
     * entitlement 필터 밖에 있어야 한다 — 아직 보유하지 않은 사용자가
     * 호출하는 액션이라, 걸면 순환이 생긴다.
     */
    public function activate(string $key): ResponseInterface
    {
        $userId = (int) session()->get('user_id');
        $plugin = (new PluginModel())->findByKey($key);

        if ($plugin === null || $plugin['status'] !== PluginModel::STATUS_ACTIVE) {
            return redirect()->to('/my/plugins')->with('error', '판매 중이 아닌 플러그인입니다.');
        }

        if ($plugin['license_type'] !== LicenseType::Credit->value) {
            return redirect()->to('/my/plugins')->with('error', '무료로 활성화할 수 없는 플러그인입니다.');
        }

        $userPlugins = new UserPluginModel();
        $existing    = $userPlugins->findEntitlement($userId, $key);

        // 회수(revoked)는 accessLevel() 이 None 이라 아래 canWrite() 검사를
        // 통과해 버린다 — 회수된 사용자가 이 액션으로 스스로 되살리지 못하게 막는다.
        if ($existing !== null && $existing->status === EntitlementStatus::Revoked) {
            return redirect()->to('/my/plugins')->with('error', '관리자에게 문의해 주세요.');
        }

        if ($existing !== null && $existing->accessLevel()->canWrite()) {
            return redirect()->to('/my/plugins')->with('error', '이미 활성화된 플러그인입니다.');
        }

        $missing = (new PluginDependencyService())->missingForUser($userId, $plugin);

        if ($missing !== []) {
            return redirect()->to('/my/plugins')->with('error', implode(', ', $missing) . ' 플러그인이 먼저 필요합니다.');
        }

        $userPlugins->grant($userId, $key, LicenseType::Credit);

        return redirect()->to('/my/plugins')->with('success', '활성화했습니다.');
    }
}
