<?php

declare(strict_types=1);

namespace Tests\Feature\Plugin;

use App\Exceptions\PluginException;
use App\Libraries\Plugin\PluginManager;
use App\Libraries\Plugin\PluginManifest;
use App\Models\PluginModel;
use Config\Database;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class PluginManagerTest extends DatabaseTestCase
{
    private string $tmpDir;
    private string $pluginsPath;
    private string $cacheFile;
    private PluginManager $manager;
    private PluginModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        // 경로를 테스트마다 무작위로 바꾸지 않는다. 플러그인 마이그레이션 클래스는
        // 파일 경로로 로드되는데, 경로가 달라지면 같은 클래스를 두 번 선언하게 되어
        // "Cannot redeclare class" 로 죽는다. 대신 매 테스트마다 내용을 갈아끼운다.
        $this->tmpDir      = WRITEPATH . 'plugin_manager_test';
        $this->pluginsPath = $this->tmpDir . '/Plugins/';
        $this->cacheFile   = $this->tmpDir . '/active.php';

        exec('rm -rf ' . escapeshellarg($this->tmpDir));
        mkdir($this->pluginsPath, 0777, true);

        foreach (['Valid', 'Broken', 'Migrating'] as $fixture) {
            exec(sprintf(
                'cp -R %s %s',
                escapeshellarg(SUPPORTPATH . 'Plugins/' . $fixture),
                escapeshellarg($this->pluginsPath),
            ));
        }

        $this->manager = new PluginManager($this->cacheFile, $this->pluginsPath);
        $this->model   = new PluginModel();
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));

        // 마이그레이션을 실행한 테스트는 DDL 의 암묵적 커밋으로 트랜잭션이 끊겨
        // 롤백에 기댈 수 없다. 직접 정리한다.
        $db = db_connect();

        if ($db->tableExists('migrating_items')) {
            Database::forge()->dropTable('migrating_items', true);
        }

        $db->table('migrations')->where('namespace', 'Plugins\\Migrating')->delete();
        $db->table('plugins')->where('plugin_key', 'migrating')->delete();

        parent::tearDown();
    }

    public function testScanSkipsBrokenManifest(): void
    {
        $keys = array_map(
            static fn (PluginManifest $manifest): string => $manifest->key,
            $this->manager->scan(),
        );

        $this->assertSame(['migrating', 'valid'], $keys);
    }

    public function testActivateRunsPluginMigrations(): void
    {
        $this->manager->sync();
        $this->manager->activate('migrating');

        $this->assertTrue(db_connect()->tableExists('migrating_items', false));
    }

    public function testUninstallRollsBackPluginMigrations(): void
    {
        $this->manager->sync();
        $this->manager->activate('migrating');
        $this->assertTrue(db_connect()->tableExists('migrating_items', false));

        $this->manager->uninstall('migrating');

        $this->assertFalse(db_connect()->tableExists('migrating_items', false));
        $this->assertNull($this->model->findByKey('migrating'));
        $this->assertDirectoryDoesNotExist($this->pluginsPath . 'Migrating');
    }

    public function testSyncRegistersAsInactive(): void
    {
        $this->manager->sync();

        $row = $this->model->findByKey('valid');
        $this->assertNotNull($row);
        $this->assertSame(PluginModel::STATUS_INACTIVE, $row['status']);
    }

    public function testSyncMarksDisappearedPluginAsMissing(): void
    {
        $this->manager->sync();
        exec('rm -rf ' . escapeshellarg($this->pluginsPath . 'Valid'));

        $this->manager->sync();

        $this->assertSame(PluginModel::STATUS_MISSING, $this->model->findByKey('valid')['status']);
    }

    public function testActivateSetsStatusAndWritesCache(): void
    {
        $this->manager->sync();
        $this->manager->activate('valid');

        $this->assertSame(PluginModel::STATUS_ACTIVE, $this->model->findByKey('valid')['status']);
        $this->assertSame(['Valid'], $this->manager->activeDirectories());
    }

    public function testDeactivateRemovesFromCache(): void
    {
        $this->manager->sync();
        $this->manager->activate('valid');
        $this->manager->deactivate('valid');

        $this->assertSame(PluginModel::STATUS_INACTIVE, $this->model->findByKey('valid')['status']);
        $this->assertSame([], $this->manager->activeDirectories());
    }

    public function testUninstallRemovesRecordAndDirectory(): void
    {
        $this->manager->sync();
        $this->manager->uninstall('valid');

        $this->assertNull($this->model->findByKey('valid'));
        $this->assertDirectoryDoesNotExist($this->pluginsPath . 'Valid');
    }

    public function testActivateUnknownKeyThrows(): void
    {
        $this->expectException(PluginException::class);
        $this->manager->activate('nope');
    }

    public function testActivateMissingPluginThrows(): void
    {
        $this->manager->sync();
        exec('rm -rf ' . escapeshellarg($this->pluginsPath . 'Valid'));
        $this->manager->sync();

        $this->expectException(PluginException::class);
        $this->manager->activate('valid');
    }
}
