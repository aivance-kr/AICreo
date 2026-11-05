<?php

declare(strict_types=1);

namespace Tests\Feature\Plugin;

use App\Libraries\Plugin\PluginManager;
use App\Models\PluginModel;
use CodeIgniter\Test\TestResponse;
use Composer\Autoload\ClassLoader;
use Config\Services;
use Plugins\Weather\Libraries\WeatherClient;
use Tests\Support\FeatureTestCase;

/**
 * 출고 날씨 위젯 플러그인(app/Plugins/Weather): 설정(지역) → 위젯 렌더.
 * 외부 API 는 부르지 않는다 — 지역별 캐시를 미리 채워 둔다.
 *
 * @internal
 */
final class WeatherPluginTest extends FeatureTestCase
{
    private string $tmpDir;
    private PluginManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = WRITEPATH . 'plugin_weather_test';
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
        mkdir($this->tmpDir . '/Plugins/', 0777, true);
        exec(sprintf('cp -R %s %s', escapeshellarg(APPPATH . 'Plugins/Weather'), escapeshellarg($this->tmpDir . '/Plugins/')));

        $this->manager = new PluginManager($this->tmpDir . '/active.php', $this->tmpDir . '/Plugins/');
        Services::injectMock('pluginManager', $this->manager);

        foreach (spl_autoload_functions() as $loader) {
            if (is_array($loader) && $loader[0] instanceof ClassLoader) {
                $loader[0]->addPsr4('Plugins\\Weather\\', $this->manager->pluginPath('Weather'));

                break;
            }
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
        cache()->clean();
        Services::reset();
        parent::tearDown();
    }

    /**
     * 마이그레이션이 없는 플러그인은 activate() 가 네임스페이스를 등록하지 않는다 (운영에서는
     * 다음 요청의 부팅 캐시가 등록). 같은 요청 안에서 뷰를 찾으려면 직접 등록한다.
     */
    private function activate(): void
    {
        $this->manager->activate('weather');
        service('autoloader')->addNamespace('Plugins\\Weather', $this->manager->pluginPath('Weather'));
    }

    private function prime(string $region, string $name, float $temperature): void
    {
        cache()->save('weather_' . md5($region), [
            'region'   => $region, 'name' => $name, 'temperature' => $temperature,
            'humidity' => 55, 'wind' => 2.5, 'label' => '맑음', 'icon' => '☀️',
        ], WeatherClient::CACHE_TTL);
    }

    private function page(): TestResponse
    {
        // 봇 UA 는 방문 로그에서 제외된다
        return $this->withHeaders(['User-Agent' => 'TestBot/1.0'])
            ->withRoutes([['get', 'weather', '\Plugins\Weather\Controllers\WeatherController::index']])
            ->get('weather');
    }

    public function testDeclaresRegionsSetting(): void
    {
        $this->manager->sync();

        $this->assertSame(['regions' => '서울'], model(PluginModel::class)->settingsFor('weather'));
    }

    /**
     * 사이트 전역 출력 필터가 비ASCII 를 숫자 엔티티로 바꾸므로 디코드해서 비교한다.
     */
    private function text(): string
    {
        return html_entity_decode((string) $this->page()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function testWidgetShowsConfiguredRegions(): void
    {
        $this->manager->sync();
        $this->activate();
        model(PluginModel::class)->saveSettings('weather', ['regions' => '부산, 제주']);
        $this->prime('부산', '부산', 18.0);
        $this->prime('제주', '제주', 20.5);

        $this->page()->assertStatus(200);
        $text = $this->text();

        $this->assertStringContainsString('18.0°C', $text);
        $this->assertStringContainsString('20.5°C', $text);
        $this->assertStringContainsString('제주', $text);
        // 설정한 두 곳만 카드로 나온다 (기본값 서울은 나오지 않는다)
        $this->assertSame(2, substr_count($text, 'fs-2 fw-bold'));
    }

    public function testFallsBackToDefaultRegionAndEscapesNames(): void
    {
        $this->manager->sync();
        $this->activate();
        $this->prime('서울', '<i>서울</i>', 10.0);

        $body = (string) $this->page()->getBody();

        $this->assertStringContainsString('&lt;i&gt;', $body);
        $this->assertStringNotContainsString('<i>', $body);
        $this->assertStringContainsString('10.0°C', $this->text());
    }

    public function testShowsFallbackWhenWeatherUnavailable(): void
    {
        $this->manager->sync();
        $this->activate();
        model(PluginModel::class)->saveSettings('weather', ['regions' => '없는곳']);
        // 캐시 없음 + 네트워크가 막혀도 실패는 null 로 처리돼 페이지는 200 이다

        $this->page()->assertStatus(200);
        $this->assertStringContainsString('없는곳', $this->text());
    }
}
