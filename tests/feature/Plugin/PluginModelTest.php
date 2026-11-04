<?php

declare(strict_types=1);

namespace Tests\Feature\Plugin;

use App\Libraries\Plugin\LicenseType;
use App\Libraries\Plugin\PluginManifest;
use App\Models\PluginModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class PluginModelTest extends DatabaseTestCase
{
    private PluginModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new PluginModel();
    }

    private function manifest(string $key = 'sample', string $version = '1.0.0'): PluginManifest
    {
        return new PluginManifest(
            key: $key,
            name: '샘플',
            version: $version,
            licenseType: LicenseType::Onetime,
            directoryName: PluginManifest::toPascalCase($key),
        );
    }

    public function testUpsertInsertsAsInactive(): void
    {
        $this->model->upsertFromManifest($this->manifest());

        $row = $this->model->findByKey('sample');

        $this->assertNotNull($row);
        $this->assertSame('sample', $row['plugin_key']);
        $this->assertSame(PluginModel::STATUS_INACTIVE, $row['status']);
        $this->assertSame('onetime', $row['license_type']);
    }

    public function testUpsertUpdatesVersionButKeepsStatus(): void
    {
        $this->model->upsertFromManifest($this->manifest());
        $this->model->update($this->model->findByKey('sample')['id'], ['status' => PluginModel::STATUS_ACTIVE]);

        $this->model->upsertFromManifest($this->manifest(version: '1.1.0'));

        $row = $this->model->findByKey('sample');
        $this->assertSame('1.1.0', $row['version']);
        $this->assertSame(PluginModel::STATUS_ACTIVE, $row['status']);
    }

    public function testMarkMissingFlagsAbsentPlugins(): void
    {
        $this->model->upsertFromManifest($this->manifest('sample'));
        $this->model->upsertFromManifest($this->manifest('gone'));

        $this->model->markMissing(['sample']);

        $this->assertSame(PluginModel::STATUS_INACTIVE, $this->model->findByKey('sample')['status']);
        $this->assertSame(PluginModel::STATUS_MISSING, $this->model->findByKey('gone')['status']);
    }

    public function testUpsertRevivesMissingPlugin(): void
    {
        $this->model->upsertFromManifest($this->manifest('sample'));
        $this->model->markMissing([]);
        $this->assertSame(PluginModel::STATUS_MISSING, $this->model->findByKey('sample')['status']);

        $this->model->upsertFromManifest($this->manifest('sample'));

        $this->assertSame(PluginModel::STATUS_INACTIVE, $this->model->findByKey('sample')['status']);
    }

    public function testUpsertDoesNotOverwriteOperatorChosenLicenseType(): void
    {
        $this->model->upsertFromManifest($this->manifest());

        // 운영자가 과금 모델을 구독으로 바꿨다
        $this->model->update($this->model->findByKey('sample')['id'], [
            'license_type'       => 'subscription',
            'price_subscription' => 9900,
        ]);

        // 재스캔해도 매니페스트 값(onetime)으로 되돌아가지 않아야 한다
        $this->model->upsertFromManifest($this->manifest(version: '1.1.0'));

        $row = $this->model->findByKey('sample');
        $this->assertSame('subscription', $row['license_type']);
        $this->assertSame('1.1.0', $row['version']);
        $this->assertSame(9900, (int) $row['price_subscription']);
    }

    public function testActiveKeysReturnsKeyToDirectoryMap(): void
    {
        $this->model->upsertFromManifest($this->manifest('sample'));
        $this->model->upsertFromManifest($this->manifest('ai_quote'));
        $this->model->update($this->model->findByKey('ai_quote')['id'], ['status' => PluginModel::STATUS_ACTIVE]);

        $this->assertSame(['ai_quote' => 'AiQuote'], $this->model->activeKeys());
    }
}
