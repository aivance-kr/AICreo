<?php

namespace Config;

use App\Libraries\Ai\GroqPostAssistService;
use App\Libraries\Ai\GroqSeriesService;
use App\Libraries\Ai\GroqTagService;
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
