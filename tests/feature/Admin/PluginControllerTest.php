<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Libraries\Plugin\PluginInstaller;
use App\Libraries\Plugin\PluginManager;
use App\Models\AdminLogModel;
use App\Models\PluginModel;
use CodeIgniter\Config\Factories;
use Config\Plugins;
use Config\Services;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class PluginControllerTest extends AdminTestCase
{
    private string $base;
    private string $pluginsPath;
    private PluginManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base        = WRITEPATH . 'plugin_admin_test';
        $this->pluginsPath = $this->base . '/Plugins/';

        exec('rm -rf ' . escapeshellarg($this->base));
        mkdir($this->pluginsPath, 0777, true);
        mkdir($this->base . '/public', 0777, true);
        mkdir($this->base . '/tmp', 0777, true);

        $this->manager = new PluginManager($this->base . '/active.php', $this->pluginsPath);
        Services::injectMock('pluginManager', $this->manager);
        Services::injectMock('pluginInstaller', new PluginInstaller(
            $this->pluginsPath,
            $this->base . '/public',
            $this->base . '/tmp',
        ));
    }

    protected function tearDown(): void
    {
        Services::reset();
        Factories::reset('config');
        exec('rm -rf ' . escapeshellarg($this->base));
        parent::tearDown();
    }

    private function addPlugin(string $key = 'demo_tool'): void
    {
        $dir = $this->pluginsPath . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/plugin.json', json_encode([
            'key' => $key, 'name' => '데모', 'version' => '1.0.0', 'license_type' => 'onetime',
        ], JSON_THROW_ON_ERROR));
    }

    private function addPluginWithSettings(): void
    {
        $dir = $this->pluginsPath . 'DemoTool';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/plugin.json', json_encode([
            'key'      => 'demo_tool', 'name' => '데모', 'version' => '1.0.0', 'license_type' => 'onetime',
            'settings' => [['key' => 'regions', 'label' => '지역', 'default' => '서울', 'help' => '도시 이름']],
        ], JSON_THROW_ON_ERROR));
    }

    public function testSettingsButtonOnlyForPluginsWithSettings(): void
    {
        $this->addPlugin('plain_tool');
        $this->addPluginWithSettings();
        $this->manager->sync();

        $result = $this->withSession($this->adminSession)->get('admin/plugins');

        $result->assertSee('/admin/plugins/demo_tool/settings');
        $result->assertDontSee('/admin/plugins/plain_tool/settings');
    }

    public function testSettingsFormShowsDefaultAndSaves(): void
    {
        $this->addPluginWithSettings();
        $this->manager->sync();

        $this->withSession($this->adminSession)->get('admin/plugins/demo_tool/settings')->assertSee('지역');

        $result = $this->withSession($this->adminSession)->post('admin/plugins/demo_tool/settings', ['regions' => '부산, 제주', 'evil' => 'x']);

        $result->assertRedirectTo('/admin/plugins');
        $this->assertSame(['regions' => '부산, 제주'], model(PluginModel::class)->settingsFor('demo_tool'));
    }

    public function testSettingsRedirectsWhenPluginHasNone(): void
    {
        $this->addPlugin();
        $this->manager->sync();

        $this->withSession($this->adminSession)->get('admin/plugins/demo_tool/settings')->assertRedirectTo('/admin/plugins');
        $this->withSession($this->adminSession)->get('admin/plugins/nope/settings')->assertRedirectTo('/admin/plugins');
    }

    public function testMemberCannotSaveSettings(): void
    {
        $this->addPluginWithSettings();
        $this->manager->sync();

        $this->withSession($this->memberSession)->post('admin/plugins/demo_tool/settings', ['regions' => 'x']);

        $this->assertSame(['regions' => '서울'], model(PluginModel::class)->settingsFor('demo_tool'));
    }

    private function allowUpload(bool $allow): void
    {
        $config              = new Plugins();
        $config->allowUpload = $allow;
        Factories::injectMock('config', 'Plugins', $config);
    }

    public function testMemberAndGuestCannotAccess(): void
    {
        $this->assertNotSame(200, $this->withSession($this->memberSession)->get('admin/plugins')->response()->getStatusCode());
        $this->assertNotSame(200, $this->get('admin/plugins')->response()->getStatusCode());
        $this->assertNotSame(200, $this->withSession($this->memberSession)->post('admin/plugins/sync')->response()->getStatusCode());
    }

    public function testListShowsRegisteredPlugins(): void
    {
        $this->addPlugin();
        $this->manager->sync();

        $result = $this->withSession($this->adminSession)->get('admin/plugins');

        $result->assertStatus(200);
        $result->assertSee('demo_tool');
        $result->assertSee('데모');
    }

    public function testSyncRegistersNewPluginAsInactive(): void
    {
        $this->addPlugin();

        $this->withSession($this->adminSession)->post('admin/plugins/sync');

        $row = model(PluginModel::class)->findByKey('demo_tool');
        $this->assertNotNull($row);
        $this->assertSame('inactive', $row['status']);
    }

    public function testActivateThenDeactivate(): void
    {
        $this->addPlugin();
        $this->manager->sync();

        $this->withSession($this->adminSession)->post('admin/plugins/demo_tool/activate');
        $this->assertSame('active', model(PluginModel::class)->findByKey('demo_tool')['status']);

        $this->withSession($this->adminSession)->post('admin/plugins/demo_tool/deactivate');
        $this->assertSame('inactive', model(PluginModel::class)->findByKey('demo_tool')['status']);
        $this->assertGreaterThanOrEqual(2, model(AdminLogModel::class)->where('target_type', 'plugin')->countAllResults());
    }

    public function testActivateMissingPluginShowsErrorAndKeepsStatus(): void
    {
        $this->addPlugin();
        $this->manager->sync();
        exec('rm -rf ' . escapeshellarg($this->pluginsPath . 'DemoTool'));
        $this->manager->sync();

        $result = $this->withSession($this->adminSession)->post('admin/plugins/demo_tool/activate');

        $result->assertRedirectTo('/admin/plugins');
        $this->assertSame('missing', model(PluginModel::class)->findByKey('demo_tool')['status']);
    }

    public function testUnknownKeyIsRejected(): void
    {
        $result = $this->withSession($this->adminSession)->post('admin/plugins/nope/activate');

        $result->assertRedirectTo('/admin/plugins');
        $this->assertNull(model(PluginModel::class)->findByKey('nope'));
    }

    public function testUninstallRemovesRecordAndDirectory(): void
    {
        $this->addPlugin();
        $this->manager->sync();

        $this->withSession($this->adminSession)->post('admin/plugins/demo_tool/uninstall');

        $this->assertNull(model(PluginModel::class)->findByKey('demo_tool'));
        $this->assertDirectoryDoesNotExist($this->pluginsPath . 'DemoTool');
    }

    public function testUploadBlockedByDefault(): void
    {
        $this->allowUpload(false);

        $result = $this->withSession($this->adminSession)->post('admin/plugins/upload');

        $result->assertRedirectTo('/admin/plugins');
        $this->assertSame([], glob($this->pluginsPath . '*'));
    }

    public function testUploadFormHiddenWhenDisabled(): void
    {
        $this->allowUpload(false);
        $this->assertStringNotContainsString('name="plugin_zip"', $this->withSession($this->adminSession)->get('admin/plugins')->getBody());

        $this->allowUpload(true);
        $this->assertStringContainsString('name="plugin_zip"', $this->withSession($this->adminSession)->get('admin/plugins')->getBody());
    }

    public function testUploadRejectsFileThatIsNotARealUpload(): void
    {
        $this->allowUpload(true);
        // 테스트에서는 is_uploaded_file() 가 항상 false 라 성공 경로는 여기서 못 탄다.
        // 대신 업로드로 보이지 않는 파일(조작된 tmp_name)이 설치되지 않는 것을 고정한다.
        $path = $this->base . '/evil.php';
        file_put_contents($path, '<?php echo 1;');
        service('superglobals')->setFilesArray(['plugin_zip' => [
            'name' => 'evil.php', 'type' => 'text/plain', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path),
        ]]);

        try {
            $this->withSession($this->adminSession)->post('admin/plugins/upload');
        } finally {
            service('superglobals')->setFilesArray([]);
        }

        $this->assertSame([], glob($this->pluginsPath . '*'));
    }
}
