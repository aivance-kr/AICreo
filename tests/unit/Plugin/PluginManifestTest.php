<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin;

use App\Exceptions\InvalidManifestException;
use App\Libraries\Plugin\LicenseType;
use App\Libraries\Plugin\PluginManifest;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
final class PluginManifestTest extends CIUnitTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function validData(): array
    {
        return [
            'key'          => 'sample',
            'name'         => '샘플',
            'version'      => '1.0.0',
            'requires'     => ['core' => '>=1.0', 'plugins' => []],
            'license_type' => 'onetime',
            'menu'         => [
                ['title' => '샘플 노트', 'route' => 'my/sample', 'icon' => 'bi-sticky'],
            ],
        ];
    }

    public function testParsesValidManifest(): void
    {
        $manifest = PluginManifest::fromArray($this->validData(), 'Sample');

        $this->assertSame('sample', $manifest->key);
        $this->assertSame('샘플', $manifest->name);
        $this->assertSame('1.0.0', $manifest->version);
        $this->assertSame(LicenseType::Onetime, $manifest->licenseType);
        $this->assertSame('Sample', $manifest->directoryName);
        $this->assertCount(1, $manifest->menu);
        $this->assertSame('my/sample', $manifest->menu[0]->route);
    }

    public function testRejectsKeyWithUppercase(): void
    {
        $data        = $this->validData();
        $data['key'] = 'Sample';

        $this->expectException(InvalidManifestException::class);
        PluginManifest::fromArray($data, 'Sample');
    }

    public function testRejectsKeyWithHyphen(): void
    {
        $data        = $this->validData();
        $data['key'] = 'my-plugin';

        $this->expectException(InvalidManifestException::class);
        PluginManifest::fromArray($data, 'MyPlugin');
    }

    public function testRejectsNonSemverVersion(): void
    {
        $data            = $this->validData();
        $data['version'] = '1.0';

        $this->expectException(InvalidManifestException::class);
        PluginManifest::fromArray($data, 'Sample');
    }

    public function testRejectsUnknownLicenseType(): void
    {
        $data                 = $this->validData();
        $data['license_type'] = 'freemium';

        $this->expectException(InvalidManifestException::class);
        PluginManifest::fromArray($data, 'Sample');
    }

    public function testRejectsMissingRequiredField(): void
    {
        $data = $this->validData();
        unset($data['name']);

        $this->expectException(InvalidManifestException::class);
        PluginManifest::fromArray($data, 'Sample');
    }

    public function testRejectsDirectoryNameMismatch(): void
    {
        // key 'sample' 의 PascalCase 는 'Sample' 이므로 'Other' 는 불일치
        $this->expectException(InvalidManifestException::class);
        PluginManifest::fromArray($this->validData(), 'Other');
    }

    public function testAcceptsSnakeCaseKeyMappedToPascalDirectory(): void
    {
        $data        = $this->validData();
        $data['key'] = 'ai_quote';

        $manifest = PluginManifest::fromArray($data, 'AiQuote');

        $this->assertSame('ai_quote', $manifest->key);
        $this->assertSame('AiQuote', $manifest->directoryName);
    }

    public function testRejectsMenuRouteWithLeadingSlash(): void
    {
        $data                     = $this->validData();
        $data['menu'][0]['route'] = '/my/sample';

        $this->expectException(InvalidManifestException::class);
        PluginManifest::fromArray($data, 'Sample');
    }

    public function testDefaultsOptionalFields(): void
    {
        $data = $this->validData();
        unset($data['requires'], $data['menu']);

        $manifest = PluginManifest::fromArray($data, 'Sample');

        $this->assertSame('*', $manifest->requiresCore);
        $this->assertSame([], $manifest->requiresPlugins);
        $this->assertSame([], $manifest->menu);
    }

    public function testRejectsMalformedJsonFile(): void
    {
        $path = WRITEPATH . 'plugin_manifest_test.json';
        file_put_contents($path, '{ not json');

        try {
            $this->expectException(InvalidManifestException::class);
            PluginManifest::fromJsonFile($path);
        } finally {
            unlink($path);
        }
    }

    public function testRejectsTooLongKey(): void
    {
        $data        = $this->validData();
        $data['key'] = str_repeat('a', 33);

        $this->expectException(InvalidManifestException::class);
        PluginManifest::fromArray($data, str_repeat('A', 1) . str_repeat('a', 32));
    }

    public function testRejectsTooLongName(): void
    {
        $data         = $this->validData();
        $data['name'] = str_repeat('가', 65);

        $this->expectException(InvalidManifestException::class);
        PluginManifest::fromArray($data, 'Sample');
    }

    public function testRejectsInvalidRequiresPlugins(): void
    {
        $data             = $this->validData();
        $data['requires'] = ['plugins' => ['Bad-Key']];

        $this->expectException(InvalidManifestException::class);
        PluginManifest::fromArray($data, 'Sample');
    }

    public function testRejectsMenuItemWithEmptyTitle(): void
    {
        $data                     = $this->validData();
        $data['menu'][0]['title'] = '';

        $this->expectException(InvalidManifestException::class);
        PluginManifest::fromArray($data, 'Sample');
    }

    public function testToArrayRoundTrips(): void
    {
        $data = [
            'key'          => 'sample',
            'name'         => '샘플',
            'version'      => '1.0.0',
            'license_type' => 'onetime',
            'requires'     => ['core' => '>=1.0', 'plugins' => ['quote']],
            'menu'         => [['title' => '샘플 노트', 'route' => 'my/sample', 'icon' => 'bi-sticky']],
            'widgets'      => [['slot' => 'sidebar', 'view' => 'Views/widgets/a', 'order' => 3, 'class' => null, 'cache' => 60, 'entitled' => true]],
        ];
        $manifest = PluginManifest::fromArray($data, 'Sample');

        $this->assertSame($data, $manifest->toArray());
    }

    public function testParsesValidJsonFileUsingParentDirectoryName(): void
    {
        $dir = WRITEPATH . 'plugin_manifest_test/Sample';
        mkdir($dir, 0o777, true);
        file_put_contents($dir . '/plugin.json', json_encode($this->validData(), JSON_THROW_ON_ERROR));

        try {
            $manifest = PluginManifest::fromJsonFile($dir . '/plugin.json');
            $this->assertSame('sample', $manifest->key);
        } finally {
            unlink($dir . '/plugin.json');
            rmdir($dir);
            rmdir(dirname($dir));
        }
    }

    public function testRejectsMissingJsonFile(): void
    {
        $this->expectException(InvalidManifestException::class);
        PluginManifest::fromJsonFile(WRITEPATH . 'no_such_plugin.json');
    }

    public function testParsesSettings(): void
    {
        $data             = $this->validData();
        $data['settings'] = [['key' => 'regions', 'label' => '지역', 'default' => '서울', 'help' => '쉼표로 구분']];

        $manifest = PluginManifest::fromArray($data, 'Sample');

        $this->assertCount(1, $manifest->settings);
        $this->assertSame('regions', $manifest->settings[0]->key);
        $this->assertSame('서울', $manifest->settings[0]->default);
        $this->assertSame($data['settings'], $manifest->toArray()['settings']);
    }

    public function testOmitsSettingsKeyWhenNone(): void
    {
        $this->assertArrayNotHasKey('settings', PluginManifest::fromArray($this->validData(), 'Sample')->toArray());
    }

    public function testRejectsInvalidOrDuplicateSettings(): void
    {
        $bad = [
            [['key' => 'Bad Key', 'label' => '가']],
            [['key' => 'a', 'label' => '']],
            [['key' => 'a', 'label' => '가'], ['key' => 'a', 'label' => '나']],
        ];

        foreach ($bad as $settings) {
            $data             = $this->validData();
            $data['settings'] = $settings;

            try {
                PluginManifest::fromArray($data, 'Sample');
                $this->fail('InvalidManifestException 이 나야 합니다');
            } catch (InvalidManifestException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testLicenseTypeLabels(): void
    {
        $this->assertSame('구독', LicenseType::Subscription->label());
    }

    public function testParsesWidgetsWithDefaults(): void
    {
        $data            = $this->validData();
        $data['widgets'] = [['slot' => 'sidebar', 'view' => 'Views/widgets/recent', 'order' => 10]];

        $widget = PluginManifest::fromArray($data, 'Sample')->widgets[0];

        $this->assertSame('sidebar', $widget->slot);
        $this->assertSame(10, $widget->order);
        $this->assertSame(300, $widget->cache);
        $this->assertFalse($widget->entitled);
        $this->assertSame('\\Plugins\\Sample\\Views\\widgets\\recent', $widget->viewName());
    }

    /**
     * @param array<string, mixed> $widget
     */
    #[DataProvider('provideRejectsInvalidWidget')]
    public function testRejectsInvalidWidget(array $widget): void
    {
        $data            = $this->validData();
        $data['widgets'] = [$widget];

        $this->expectException(InvalidManifestException::class);
        PluginManifest::fromArray($data, 'Sample');
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideRejectsInvalidWidget(): iterable
    {
        yield 'slot 대문자' => [['slot' => 'Side', 'view' => 'Views/a']];

        yield 'view 상위 경로' => [['slot' => 'sidebar', 'view' => 'Views/../x']];

        yield 'view Views 아님' => [['slot' => 'sidebar', 'view' => 'Other/a']];

        yield '다른 네임스페이스 class' => [['slot' => 'sidebar', 'view' => 'Views/a', 'class' => 'App\\Evil']];

        yield 'cache 음수' => [['slot' => 'sidebar', 'view' => 'Views/a', 'cache' => -1]];

        yield 'order 문자열' => [['slot' => 'sidebar', 'view' => 'Views/a', 'order' => 'x']];
    }
}
