<?php

declare(strict_types=1);

namespace Tests\Feature\Plugin;

use App\Libraries\Plugin\PluginManager;
use App\Models\PluginModel;
use CodeIgniter\Events\Events;
use CodeIgniter\Router\RouteCollection;
use CodeIgniter\Router\Router;
use Composer\Autoload\ClassLoader;
use Config\Database;
use Config\Modules;
use Config\Routing;
use Config\Services;
use Plugins\Sample\Libraries\NoteAddedListener;
use Plugins\Sample\Models\SampleNoteModel;
use Tests\Support\FeatureTestCase;

/**
 * 출고 샘플 플러그인(app/Plugins/Sample)의 전 경로 고정:
 * 스캔 → 활성화(마이그레이션) → 라우트 → 렌더 → 이벤트 → 비활성화(데이터 유지) → 제거(테이블 삭제).
 *
 * 제거는 디렉터리를 지우므로 실제 app/Plugins 가 아니라 임시 디렉터리로 복사해서 쓴다.
 * 경로는 고정이다 — 플러그인 클래스가 경로로 오토로드돼 매번 바꾸면 중복 선언으로 죽는다.
 *
 * @internal
 */
final class SamplePluginTest extends FeatureTestCase
{
    private string $tmpDir;
    private PluginManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = WRITEPATH . 'plugin_sample_test';
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
        mkdir($this->tmpDir . '/Plugins/', 0777, true);
        exec(sprintf(
            'cp -R %s %s',
            escapeshellarg(APPPATH . 'Plugins/Sample'),
            escapeshellarg($this->tmpDir . '/Plugins/'),
        ));

        $this->manager = new PluginManager($this->tmpDir . '/active.php', $this->tmpDir . '/Plugins/');
        Services::injectMock('pluginManager', $this->manager);

        $this->registerSampleNamespace();

        NoteAddedListener::reset();
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));

        // DDL 암묵 커밋으로 트랜잭션 롤백에 기댈 수 없다 — 직접 정리
        Database::forge()->dropTable('sample_notes', true);
        db_connect()->table('migrations')->like('namespace', 'Plugins\\Sample', 'after')->delete();
        db_connect()->table('plugins')->where('plugin_key', 'sample')->delete();

        Services::reset();
        Events::removeAllListeners();
        parent::tearDown();
    }

    /**
     * 리스너·모델·컨트롤러 클래스를 테스트에서 직접 쓰므로 네임스페이스를 등록한다
     * (운영에서는 부팅 시 활성 캐시가 등록해 준다).
     *
     * service('autoloader') 에 등록하지 않는 이유: 앞선 테스트의 Services::reset() 이
     * 그 인스턴스를 갈아끼워, 새 인스턴스는 SPL 에 걸려 있지 않아 클래스 로딩에 반영되지 않는다.
     * 대신 항상 살아 있는 Composer 로더에 PSR-4 로 등록한다.
     */
    private function registerSampleNamespace(): void
    {
        foreach (spl_autoload_functions() as $loader) {
            if (is_array($loader) && $loader[0] instanceof ClassLoader) {
                $loader[0]->addPsr4('Plugins\\Sample\\', $this->manager->pluginPath('Sample'));

                return;
            }
        }
    }

    private function routes(): RouteCollection
    {
        $routes = new RouteCollection(service('locator'), config(Modules::class), config(Routing::class));
        $this->manager->loadRoutes($routes);
        $routes->get('(:segment)', 'Front\PageController::show/$1');

        return $routes;
    }

    private function controllerFor(string $uri): string
    {
        $router = new Router($this->routes(), service('request'));
        $router->handle($uri);

        return $router->controllerName();
    }

    public function testShippedManifestIsValidAndScanned(): void
    {
        $keys = array_map(static fn ($m): string => $m->key, $this->manager->scan());

        $this->assertSame(['sample'], $keys);
    }

    public function testFullLifecycle(): void
    {
        // 스캔 → 등록(비활성): 테이블·라우트 없음
        $this->manager->sync();
        $this->assertSame(PluginModel::STATUS_INACTIVE, model(PluginModel::class)->findByKey('sample')['status']);
        $this->assertFalse(db_connect()->tableExists('sample_notes'));
        $this->assertSame('\App\Controllers\Front\PageController', $this->controllerFor('sample'));

        // 활성화: 마이그레이션 실행 + 라우트 수집(catch-all 보다 먼저 매칭)
        $this->manager->activate('sample');
        $this->assertTrue(db_connect()->tableExists('sample_notes'));

        $this->assertSame('\Plugins\Sample\Controllers\SampleController', $this->controllerFor('sample'));

        // 비활성화: 라우트는 내려가고 데이터는 유지
        $note = model(SampleNoteModel::class)->add('남아야 하는 메모');
        $this->manager->deactivate('sample');
        $this->assertSame('\App\Controllers\Front\PageController', $this->controllerFor('sample'));
        $this->assertTrue(db_connect()->tableExists('sample_notes'));
        $this->assertSame(1, db_connect()->table('sample_notes')->where('id', $note)->countAllResults());

        // 제거: 마이그레이션 rollback + 레코드·디렉터리 삭제, 코어 테이블은 보존
        $this->manager->activate('sample');
        $this->manager->uninstall('sample');
        $this->assertFalse(db_connect()->tableExists('sample_notes'));
        $this->assertNull(model(PluginModel::class)->findByKey('sample'));
        $this->assertDirectoryDoesNotExist($this->tmpDir . '/Plugins/Sample');
        $this->assertTrue(db_connect()->tableExists('plugins'));
        $this->assertTrue(db_connect()->tableExists('users'));
    }

    public function testActiveRouteRendersNotesEscaped(): void
    {
        $this->manager->sync();
        $this->manager->activate('sample');
        model(SampleNoteModel::class)->add('<b>hi</b>');

        $result = $this->withRoutes([['get', 'sample', '\Plugins\Sample\Controllers\SampleController::index']])
            ->get('sample');

        $result->assertStatus(200);
        $result->assertSee('샘플 플러그인');
        $this->assertStringContainsString('&lt;b&gt;hi&lt;/b&gt;', (string) $result->getBody());
        $this->assertStringNotContainsString('<b>hi</b>', (string) $result->getBody());
    }

    public function testEventsAreSubscribedOnlyWhileActive(): void
    {
        $this->manager->sync();
        $this->manager->activate('sample');
        $this->manager->loadEvents();

        $id = model(SampleNoteModel::class)->add('이벤트');

        $this->assertSame([$id], NoteAddedListener::received());

        // 비활성이면 Events.php 가 로드되지 않아 구독이 생기지 않는다
        Events::removeAllListeners();
        NoteAddedListener::reset();
        $this->manager->deactivate('sample');
        $this->manager->loadEvents();

        model(SampleNoteModel::class)->add('무시됨');

        $this->assertSame([], NoteAddedListener::received());
    }
}
