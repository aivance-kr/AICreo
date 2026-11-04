<?php

declare(strict_types=1);

namespace Tests\Feature\Plugin;

use App\Exceptions\InvalidManifestException;
use App\Exceptions\PluginException;
use App\Libraries\Plugin\PluginInstaller;
use CodeIgniter\Test\CIUnitTestCase;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * @internal
 */
final class PluginInstallerTest extends CIUnitTestCase
{
    private string $base;
    private string $pluginsPath;
    private string $publicPath;
    private string $tmpRoot;
    private PluginInstaller $installer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base        = WRITEPATH . 'plugin_installer_test';
        $this->pluginsPath = $this->base . '/Plugins';
        $this->publicPath  = $this->base . '/public_plugins';
        $this->tmpRoot     = $this->base . '/tmp';

        exec('rm -rf ' . escapeshellarg($this->base));
        mkdir($this->pluginsPath, 0777, true);
        mkdir($this->publicPath, 0777, true);
        mkdir($this->tmpRoot, 0777, true);

        $this->installer = new PluginInstaller($this->pluginsPath, $this->publicPath, $this->tmpRoot);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->base));
        parent::tearDown();
    }

    /**
     * @param array<string, string> $entries 이름 => 내용
     */
    private function makeZip(array $entries): string
    {
        $path = $this->base . '/' . bin2hex(random_bytes(4)) . '.zip';
        $zip  = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);

        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return $path;
    }

    private function manifestJson(string $key = 'demo_tool', string $version = '1.0.0'): string
    {
        return json_encode(['key' => $key, 'name' => '데모', 'version' => $version, 'license_type' => 'onetime'], JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string> 목적지 트리에 남은 파일 (임시 디렉터리 제외)
     */
    private function destinationFiles(): array
    {
        $out = [];

        foreach ([$this->pluginsPath, $this->publicPath] as $root) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }

    public function testInstallsPluginAndMovesPublic(): void
    {
        $zip = $this->makeZip([
            'plugin.json'        => $this->manifestJson(),
            'Routes.php'         => "<?php\n",
            'Controllers/A.php'  => "<?php\n",
            'public/css/app.css' => 'a{}',
        ]);

        $manifest = $this->installer->install($zip);

        $this->assertSame('demo_tool', $manifest->key);
        $this->assertFileExists($this->pluginsPath . '/DemoTool/plugin.json');
        $this->assertFileExists($this->pluginsPath . '/DemoTool/Controllers/A.php');
        $this->assertFileExists($this->publicPath . '/demo_tool/css/app.css');
        $this->assertDirectoryDoesNotExist($this->pluginsPath . '/DemoTool/public');
        $this->assertSame([], glob($this->tmpRoot . '/*'), '임시 디렉터리 정리');
    }

    public function testDirectoryNameDerivedFromKeyNotZipFolder(): void
    {
        $zip = $this->makeZip(['plugin.json' => $this->manifestJson('ai_quote')]);

        $this->installer->install($zip);

        $this->assertDirectoryExists($this->pluginsPath . '/AiQuote');
    }

    #[DataProvider('provideRejectsMaliciousPath')]
    public function testRejectsMaliciousPath(string $name): void
    {
        $zip = $this->makeZip(['plugin.json' => $this->manifestJson(), $name => '<?php']);

        $this->expectException(PluginException::class);

        try {
            $this->installer->install($zip);
        } finally {
            $this->assertSame([], $this->destinationFiles());
            $this->assertFileDoesNotExist($this->base . '/evil.php');
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideRejectsMaliciousPath(): iterable
    {
        return [
            'dotdot'        => ['../evil.php'],
            'nested dotdot' => ['a/../../evil.php'],
            'absolute'      => ['/etc/evil.php'],
            'backslash'     => ['a\\evil.php'],
            'drive prefix'  => ['C:evil.php'],
        ];
    }

    public function testRejectsForbiddenExtension(): void
    {
        foreach (['run.phtml', 'x.sh', 'noext', '.htaccess', 'a.php.exe'] as $name) {
            $zip = $this->makeZip(['plugin.json' => $this->manifestJson(), $name => 'x']);

            try {
                $this->installer->install($zip);
                $this->fail("{$name} 가 통과했다");
            } catch (PluginException) {
                $this->assertSame([], $this->destinationFiles());
            }
        }
    }

    public function testRejectsTooManyEntries(): void
    {
        $entries = ['plugin.json' => $this->manifestJson()];

        for ($i = 0; $i <= PluginInstaller::MAX_ENTRIES; $i++) {
            $entries["f{$i}.md"] = '';
        }

        $this->expectException(PluginException::class);

        try {
            $this->installer->install($this->makeZip($entries));
        } finally {
            $this->assertSame([], $this->destinationFiles());
        }
    }

    public function testRejectsOversizedContent(): void
    {
        $zip = $this->makeZip([
            'plugin.json' => $this->manifestJson(),
            'big.md'      => str_repeat('a', PluginInstaller::MAX_TOTAL_BYTES + 1),
        ]);

        $this->expectException(PluginException::class);

        try {
            $this->installer->install($zip);
        } finally {
            $this->assertSame([], $this->destinationFiles());
            $this->assertSame([], glob($this->tmpRoot . '/*'));
        }
    }

    public function testRejectsMissingTopLevelManifest(): void
    {
        $zip = $this->makeZip(['Nested/plugin.json' => $this->manifestJson(), 'Routes.php' => '<?php']);

        $this->expectException(PluginException::class);

        try {
            $this->installer->install($zip);
        } finally {
            $this->assertSame([], $this->destinationFiles());
        }
    }

    public function testRejectsInvalidManifest(): void
    {
        $zip = $this->makeZip(['plugin.json' => $this->manifestJson('Bad-Key')]);

        $this->expectException(InvalidManifestException::class);

        try {
            $this->installer->install($zip);
        } finally {
            $this->assertSame([], $this->destinationFiles());
            $this->assertSame([], glob($this->tmpRoot . '/*'));
        }
    }

    public function testRejectsNonZipFile(): void
    {
        $path = $this->base . '/not.zip';
        file_put_contents($path, 'plain text');

        $this->expectException(PluginException::class);
        $this->installer->install($path);
    }

    public function testReplacesExistingInstall(): void
    {
        $this->installer->install($this->makeZip([
            'plugin.json' => $this->manifestJson(version: '1.0.0'),
            'old.php'     => '<?php',
            'public/o.js' => '1',
        ]));
        $this->installer->install($this->makeZip([
            'plugin.json' => $this->manifestJson(version: '2.0.0'),
            'new.php'     => '<?php',
        ]));

        $this->assertFileExists($this->pluginsPath . '/DemoTool/new.php');
        $this->assertFileDoesNotExist($this->pluginsPath . '/DemoTool/old.php');
        $this->assertDirectoryDoesNotExist($this->publicPath . '/demo_tool');
    }

    public function testFailedReplaceKeepsExistingInstall(): void
    {
        $this->installer->install($this->makeZip(['plugin.json' => $this->manifestJson(), 'keep.php' => '<?php']));

        try {
            $this->installer->install($this->makeZip(['plugin.json' => $this->manifestJson(), 'x.sh' => 'x']));
            $this->fail('예외 기대');
        } catch (PluginException) {
            $this->assertFileExists($this->pluginsPath . '/DemoTool/keep.php');
        }
    }
}
