<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\InvalidManifestException;
use App\Exceptions\PluginException;
use App\Libraries\MarkdownRenderer;
use App\Libraries\Plugin\PluginInstaller;
use App\Libraries\Plugin\PluginManager;
use App\Libraries\Plugin\WidgetRenderer;
use App\Models\AdminLogModel;
use App\Models\PluginModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Plugins;

/**
 * 플러그인 관리. 목록·재스캔·활성/비활성·제거·ZIP 업로드.
 *
 * 로직은 PluginManager/PluginInstaller 에 있고, 여기서는 입력 확인 → 위임 → 응답만 한다.
 */
class PluginController extends BaseController
{
    private const LIST_URL = '/admin/plugins';

    public function index(): string
    {
        $model   = model(PluginModel::class);
        $plugins = $model->orderBy('plugin_key')->findAll();

        foreach ($plugins as &$plugin) {
            $plugin['has_settings'] = $model->definitionsOf($plugin) !== [];
            $plugin['has_readme']   = $this->manager()->readme((string) $plugin['plugin_key']) !== null;
        }
        unset($plugin);

        return $this->render('admin/plugins/index', [
            'plugins'     => $plugins,
            'allowUpload' => config(Plugins::class)->allowUpload,
        ]);
    }

    public function sync(): ResponseInterface
    {
        $this->manager()->sync();

        return redirect()->to(self::LIST_URL)->with('success', '플러그인 폴더를 다시 읽었습니다.');
    }

    public function activate(string $key): ResponseInterface
    {
        return $this->change($key, 'toggle', '활성화', fn () => $this->manager()->activate($key));
    }

    public function deactivate(string $key): ResponseInterface
    {
        return $this->change($key, 'toggle', '비활성화', fn () => $this->manager()->deactivate($key));
    }

    public function uninstall(string $key): ResponseInterface
    {
        return $this->change($key, 'delete', '제거', fn () => $this->manager()->uninstall($key));
    }

    public function settings(string $key): ResponseInterface|string
    {
        $model = model(PluginModel::class);
        $row   = $model->findByKey($key);

        if ($row === null || $model->settingDefinitions($key) === []) {
            return redirect()->to(self::LIST_URL)->with('error', '설정할 항목이 없는 플러그인입니다.');
        }

        return $this->render('admin/plugins/settings', [
            'plugin'      => $row,
            'definitions' => $model->settingDefinitions($key),
            'values'      => $model->settingsFor($key),
        ]);
    }

    public function readme(string $key): ResponseInterface|string
    {
        $row      = model(PluginModel::class)->findByKey($key);
        $markdown = $row === null ? null : $this->manager()->readme($key);

        if ($row === null || $markdown === null) {
            return redirect()->to(self::LIST_URL)->with('error', '설명 문서가 없는 플러그인입니다.');
        }

        return $this->render('admin/plugins/readme', [
            'plugin'     => $row,
            'readmeHtml' => MarkdownRenderer::toHtml($markdown),
        ]);
    }

    public function saveSettings(string $key): ResponseInterface
    {
        $model = model(PluginModel::class);
        $row   = $model->findByKey($key);

        if ($row === null || $model->settingDefinitions($key) === []) {
            return redirect()->to(self::LIST_URL)->with('error', '설정할 항목이 없는 플러그인입니다.');
        }

        $model->saveSettings($key, (array) $this->request->getPost());
        WidgetRenderer::clearCache($key);
        // 값은 남기지 않고 항목 이름만 (활동 로그 규약)
        model(AdminLogModel::class)->record('update', 'plugin', (int) $row['id'], $key . ' 설정');

        return redirect()->to(self::LIST_URL)->with('success', "{$row['name']} 설정을 저장했습니다.");
    }

    public function upload(): ResponseInterface
    {
        if (! config(Plugins::class)->allowUpload) {
            return redirect()->to(self::LIST_URL)->with('error', 'ZIP 업로드가 꺼져 있습니다. 서버 .env 의 plugins.allowUpload 로 켤 수 있습니다.');
        }

        $file = $this->request->getFile('plugin_zip');

        if ($file === null || ! $file->isValid() || strtolower($file->getClientExtension()) !== 'zip') {
            return redirect()->to(self::LIST_URL)->with('error', 'ZIP 파일을 선택해 주세요.');
        }

        try {
            $manifest = $this->installer()->install($file->getTempName());
        } catch (InvalidManifestException|PluginException $e) {
            return redirect()->to(self::LIST_URL)->with('error', '설치하지 못했습니다: ' . $e->getMessage());
        }

        $this->manager()->sync();
        model(AdminLogModel::class)->record('create', 'plugin', null, $manifest->key . ' ' . $manifest->version);

        return redirect()->to(self::LIST_URL)->with('success', "{$manifest->name} 을(를) 설치했습니다. 비활성 상태이니 확인 후 활성화하세요.");
    }

    /**
     * 키로 대상 확인 → 동작 실행 → 활동 로그. 실패 메시지는 도메인 예외 문구를 그대로 보여 준다.
     *
     * @param callable(): void $action
     */
    private function change(string $key, string $logAction, string $label, callable $action): ResponseInterface
    {
        $row = model(PluginModel::class)->findByKey($key);

        if ($row === null) {
            return redirect()->to(self::LIST_URL)->with('error', '플러그인을 찾을 수 없습니다.');
        }

        try {
            $action();
        } catch (PluginException $e) {
            return redirect()->to(self::LIST_URL)->with('error', $e->getMessage());
        }

        model(AdminLogModel::class)->record($logAction, 'plugin', (int) $row['id'], $key . ' ' . $label);

        return redirect()->to(self::LIST_URL)->with('success', "{$row['name']} {$label}했습니다.");
    }

    private function manager(): PluginManager
    {
        return service('pluginManager');
    }

    private function installer(): PluginInstaller
    {
        return service('pluginInstaller');
    }
}
