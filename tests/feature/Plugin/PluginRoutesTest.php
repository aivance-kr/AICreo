<?php

declare(strict_types=1);

namespace Tests\Feature\Plugin;

use App\Libraries\Plugin\PluginManager;
use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Router\Router;
use Config\Modules;
use Config\Routing;
use Tests\Support\DatabaseTestCase;

/**
 * 플러그인 라우트 수집 — 활성만 수집되고, 코어 catch-all 보다 먼저 매칭돼야 한다.
 *
 * @internal
 */
final class PluginRoutesTest extends DatabaseTestCase
{
    private string $tmpDir;
    private PluginManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = WRITEPATH . 'plugin_routes_test';
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
        mkdir($this->tmpDir . '/Plugins/', 0777, true);
        exec(sprintf(
            'cp -R %s %s',
            escapeshellarg(SUPPORTPATH . 'Plugins/Valid'),
            escapeshellarg($this->tmpDir . '/Plugins/'),
        ));

        $this->manager = new PluginManager($this->tmpDir . '/active.php', $this->tmpDir . '/Plugins/');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
        parent::tearDown();
    }

    private function collection(): RouteCollection
    {
        return new RouteCollection(service('locator'), config(Modules::class), config(Routing::class));
    }

    public function testActivePluginRouteMatchesBeforeCatchAll(): void
    {
        $this->manager->sync();
        $this->manager->activate('valid');

        $routes = $this->collection();
        $this->manager->loadRoutes($routes);
        $routes->get('(:segment)', 'Front\PageController::show/$1');

        $router = new Router($routes, service('request'));
        $router->handle('my/valid');

        $this->assertSame('\Plugins\Valid\Controllers\ValidController', $router->controllerName());
    }

    public function testInactivePluginRouteIsNotCollected(): void
    {
        $this->manager->sync();

        $routes = $this->collection();
        $this->manager->loadRoutes($routes);

        $this->assertArrayNotHasKey('my/valid', $routes->getRoutes('get'));
    }

    public function testCoreRoutesLoadPluginsAboveBothCatchAlls(): void
    {
        $source = (string) file_get_contents(APPPATH . 'Config/Routes.php');

        $load      = strpos($source, "service('pluginManager')->loadRoutes(");
        $localized = strpos($source, "'/(:segment)', 'Front\\PageController::show/\$1'");
        $plain     = strpos($source, "\$routes->get('(:segment)', 'Front\\PageController::show/\$1')");

        $this->assertNotFalse($load);
        $this->assertNotFalse($localized);
        $this->assertNotFalse($plain);
        $this->assertLessThan($localized, $load);
        $this->assertLessThan($plain, $load);
    }
}
