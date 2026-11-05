<?php

declare(strict_types=1);

namespace Tests\Feature\Plugin;

use App\Libraries\Plugin\LicenseType;
use App\Libraries\Plugin\PluginManager;
use App\Libraries\Plugin\WidgetDefinition;
use App\Libraries\Plugin\WidgetRenderer;
use App\Models\UserPluginModel;
use Composer\Autoload\ClassLoader;
use Config\Database;
use Config\Services;
use Plugins\Sample\Models\SampleNoteModel;
use Tests\Support\FeatureTestCase;

/**
 * 위젯 슬롯: 활성 플러그인 수집·정렬, 렌더 격리, Entitlement 숨김, 캐시.
 *
 * Sample 플러그인(sidebar 위젯 포함)을 임시 디렉터리로 복사해 쓴다. 경로는 SamplePluginTest 와 같아야 한다
 * (마이그레이션 클래스가 경로로 로드돼 달라지면 중복 선언으로 죽는다).
 *
 * @internal
 */
final class WidgetSlotTest extends FeatureTestCase
{
    private string $tmpDir;
    private PluginManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = WRITEPATH . 'plugin_sample_test';
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
        mkdir($this->tmpDir . '/Plugins/', 0777, true);
        exec(sprintf('cp -R %s %s', escapeshellarg(APPPATH . 'Plugins/Sample'), escapeshellarg($this->tmpDir . '/Plugins/')));

        $this->manager = new PluginManager($this->tmpDir . '/active.php', $this->tmpDir . '/Plugins/');
        Services::injectMock('pluginManager', $this->manager);

        foreach (spl_autoload_functions() as $loader) {
            if (is_array($loader) && $loader[0] instanceof ClassLoader) {
                $loader[0]->addPsr4('Plugins\\Sample\\', $this->manager->pluginPath('Sample'));

                break;
            }
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
        $this->db->transRollback();
        Database::forge()->dropTable('sample_notes', true);
        db_connect()->table('migrations')->like('namespace', 'Plugins\\Sample', 'after')->delete();
        db_connect()->table('plugins')->where('plugin_key', 'sample')->delete();
        // activate() 의 DDL 이 트랜잭션을 커밋하므로 그 뒤의 grant 는 롤백되지 않는다
        db_connect()->table('user_plugins')->where('plugin_key', 'sample')->delete();
        cache()->clean();
        Services::reset();
        parent::tearDown();
    }

    private function activate(): void
    {
        $this->manager->sync();
        $this->manager->activate('sample');
    }

    public function testWidgetsOnlyFromActivePluginsAndCleared(): void
    {
        $this->manager->sync();
        $this->assertSame([], $this->manager->widgets('sidebar'));

        $this->manager->activate('sample');
        $widgets = $this->manager->widgets('sidebar');
        $this->assertCount(1, $widgets);
        $this->assertSame('sample', $widgets[0]->pluginKey);
        $this->assertSame([], $this->manager->widgets('footer'));

        $this->manager->deactivate('sample');
        $this->assertSame([], $this->manager->widgets('sidebar'));
    }

    public function testRendersActiveWidgetAndEscapes(): void
    {
        $this->activate();
        model(SampleNoteModel::class)->add('<b>x</b>');

        $html = widget_slot('sidebar');

        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);

        $this->manager->deactivate('sample');
        $this->assertSame('', widget_slot('sidebar'));
    }

    public function testBrokenWidgetIsIsolated(): void
    {
        $this->activate();
        $broken = new WidgetDefinition('sample', 'Sample', 'sidebar', 'Views/widgets/recent', 1, 'Plugins\\Sample\\Widgets\\Missing', 0);
        $good   = new WidgetDefinition('sample', 'Sample', 'sidebar', 'Views/widgets/recent', 2, 'Plugins\\Sample\\Widgets\\RecentNotesWidget', 0);
        model(SampleNoteModel::class)->add('살아있음');

        $plugins = new class ($broken, $good) extends PluginManager {
            public function __construct(private WidgetDefinition $a, private WidgetDefinition $b)
            {
            }

            public function widgets(string $slot): array
            {
                return [$this->a, $this->b];
            }
        };

        $this->assertStringContainsString('살아있음', (new WidgetRenderer($plugins))->render('sidebar'));
    }

    public function testEntitledWidgetHiddenFromNonHolder(): void
    {
        $this->activate();
        model(SampleNoteModel::class)->add('유료 위젯');
        $def = new WidgetDefinition('sample', 'Sample', 'sidebar', 'Views/widgets/recent', 0, 'Plugins\\Sample\\Widgets\\RecentNotesWidget', 0, true);

        $plugins = new class ($def) extends PluginManager {
            public function __construct(private WidgetDefinition $d)
            {
            }

            public function widgets(string $slot): array
            {
                return [$this->d];
            }
        };
        $renderer = new WidgetRenderer($plugins);

        $this->assertSame('', $renderer->render('sidebar'));

        $_SESSION['user_id'] = 2;
        $this->assertSame('', $renderer->render('sidebar'));

        (new UserPluginModel())->grant(2, 'sample', LicenseType::Onetime);
        $this->assertStringContainsString('유료 위젯', $renderer->render('sidebar'));
        unset($_SESSION['user_id']);
    }

    public function testRenderedHtmlIsCached(): void
    {
        $this->activate();
        model(SampleNoteModel::class)->add('처음');
        $def = new WidgetDefinition('sample', 'Sample', 'sidebar', 'Views/widgets/recent', 0, 'Plugins\\Sample\\Widgets\\RecentNotesWidget', 60);

        $plugins = new class ($def) extends PluginManager {
            public function __construct(private WidgetDefinition $d)
            {
            }

            public function widgets(string $slot): array
            {
                return [$this->d];
            }
        };
        $renderer = new WidgetRenderer($plugins);

        $this->assertStringContainsString('처음', $renderer->render('sidebar'));
        model(SampleNoteModel::class)->add('나중');
        $this->assertStringNotContainsString('나중', $renderer->render('sidebar'));
    }
}
