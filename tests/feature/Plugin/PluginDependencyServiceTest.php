<?php

declare(strict_types=1);

namespace Tests\Feature\Plugin;

use App\Libraries\Plugin\LicenseType;
use App\Libraries\Plugin\PluginDependencyService;
use App\Models\PluginModel;
use App\Models\UserPluginModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class PluginDependencyServiceTest extends DatabaseTestCase
{
    private function seedPlugin(string $key, string $name, ?array $requires = null): array
    {
        $manifest = ['key' => $key] + ($requires === null ? [] : ['requires' => ['plugins' => $requires]]);
        $model    = new PluginModel();
        $model->insert([
            'plugin_key'   => $key,
            'name'         => $name,
            'version'      => '1.0.0',
            'status'       => PluginModel::STATUS_ACTIVE,
            'license_type' => LicenseType::Onetime->value,
            'manifest'     => json_encode($manifest, JSON_THROW_ON_ERROR),
        ]);

        return $model->findByKey($key);
    }

    public function testNoRequirementsMeansNothingMissing(): void
    {
        $plugin = $this->seedPlugin('solo', '단독');

        $this->assertSame([], (new PluginDependencyService())->missingForUser(7, $plugin));
    }

    public function testMissingRequirementReturnsDisplayName(): void
    {
        $this->seedPlugin('base', '기본');
        $addon = $this->seedPlugin('addon', '애드온', ['base']);

        $this->assertSame(['기본'], (new PluginDependencyService())->missingForUser(7, $addon));
    }

    public function testOwnedRequirementIsSatisfied(): void
    {
        $this->seedPlugin('base', '기본');
        $addon = $this->seedPlugin('addon', '애드온', ['base']);
        (new UserPluginModel())->grant(7, 'base', LicenseType::Onetime);

        $this->assertSame([], (new PluginDependencyService())->missingForUser(7, $addon));
    }

    public function testRevokedRequirementCountsAsMissing(): void
    {
        $this->seedPlugin('base', '기본');
        $addon = $this->seedPlugin('addon', '애드온', ['base']);
        $model = new UserPluginModel();
        $model->grant(7, 'base', LicenseType::Onetime);
        $model->revoke(7, 'base');

        $this->assertSame(['기본'], (new PluginDependencyService())->missingForUser(7, $addon));
    }

    public function testOtherUsersOwnershipDoesNotCount(): void
    {
        $this->seedPlugin('base', '기본');
        $addon = $this->seedPlugin('addon', '애드온', ['base']);
        (new UserPluginModel())->grant(8, 'base', LicenseType::Onetime);

        $this->assertSame(['기본'], (new PluginDependencyService())->missingForUser(7, $addon));
    }
}
