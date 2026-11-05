<?php

namespace Config;

use App\Libraries\Ai\GroqPostAssistService;
use App\Libraries\Ai\GroqSeriesService;
use App\Libraries\Ai\GroqTagService;
use App\Libraries\Plugin\PluginInstaller;
use App\Libraries\Plugin\PluginManager;
use App\Libraries\Seo\IndexNowService;
use App\Libraries\ThemeView;
use CodeIgniter\Config\BaseService;
use CodeIgniter\View\RendererInterface;
use Config\View as ViewConfig;

class Services extends BaseService
{
    public static function groqTags(bool $getShared = true): GroqTagService
    {
        if ($getShared) {
            return static::getSharedInstance('groqTags');
        }

        return new GroqTagService();
    }

    public static function groqPostAssist(bool $getShared = true): GroqPostAssistService
    {
        if ($getShared) {
            return static::getSharedInstance('groqPostAssist');
        }

        return new GroqPostAssistService();
    }

    public static function groqSeries(bool $getShared = true): GroqSeriesService
    {
        if ($getShared) {
            return static::getSharedInstance('groqSeries');
        }

        return new GroqSeriesService();
    }

    public static function indexnow(bool $getShared = true): IndexNowService
    {
        if ($getShared) {
            return static::getSharedInstance('indexnow');
        }

        return new IndexNowService();
    }

    /**
     * 플러그인 레지스트리. 테스트 환경은 격리된 캐시(active_test.php)를 써서
     * 개발 서버의 실제 캐시(active.php)를 덮어쓰지 않는다.
     */
    public static function pluginManager(bool $getShared = true): PluginManager
    {
        if ($getShared) {
            return static::getSharedInstance('pluginManager');
        }

        return new PluginManager(ENVIRONMENT === 'testing' ? WRITEPATH . 'plugins/active_test.php' : null);
    }

    public static function pluginInstaller(bool $getShared = true): PluginInstaller
    {
        if ($getShared) {
            return static::getSharedInstance('pluginInstaller');
        }

        return new PluginInstaller();
    }

    /**
     * @return RendererInterface
     */
    public static function renderer(?string $viewPath = null, ?ViewConfig $config = null, bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('renderer', $viewPath, $config);
        }

        $viewPath ??= (new Paths())->viewDirectory;
        $config ??= config(ViewConfig::class);

        return new ThemeView(
            $config,
            $viewPath,
            \CodeIgniter\Config\Services::get('locator'),
            CI_DEBUG,
            \CodeIgniter\Config\Services::get('logger'),
        );
    }
}
