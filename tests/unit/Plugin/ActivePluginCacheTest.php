<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin;

use App\Libraries\Plugin\ActivePluginCache;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ActivePluginCacheTest extends CIUnitTestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/plugin_cache_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/Plugins', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
        parent::tearDown();
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function makePlugin(string $name): void
    {
        mkdir($this->root . '/Plugins/' . $name, 0775, true);
        file_put_contents($this->root . '/Plugins/' . $name . '/plugin.json', '{}');
    }

    private function cache(): ActivePluginCache
    {
        return new ActivePluginCache($this->root . '/state/active.php', $this->root . '/Plugins');
    }

    public function testMissingFileReturnsEmpty(): void
    {
        $this->assertSame([], $this->cache()->psr4Map());
    }

    public function testBrokenFileReturnsEmpty(): void
    {
        mkdir($this->root . '/state', 0775, true);
        file_put_contents($this->root . '/state/active.php', '<?php return [;');

        $this->assertSame([], $this->cache()->psr4Map());
    }

    public function testNonArrayReturnsEmpty(): void
    {
        mkdir($this->root . '/state', 0775, true);
        file_put_contents($this->root . '/state/active.php', '<?php return "Quote";');

        $this->assertSame([], $this->cache()->psr4Map());
    }

    public function testWriteThenReadMapsNamespaces(): void
    {
        $this->makePlugin('Quote');
        $cache = $this->cache();
        $cache->write(['quote' => 'Quote']);

        $this->assertSame(
            ['Plugins\Quote' => $this->root . '/Plugins/Quote'],
            $cache->psr4Map(),
        );
    }

    public function testRejectsPathEscapingNames(): void
    {
        $this->makePlugin('Quote');
        $cache = $this->cache();
        $cache->write([
            'a' => '../Evil',
            'b' => 'quote',
            'c' => 'Quote/../Quote',
            'd' => 'Quo te',
            'e' => 'Quote',
        ]);

        $this->assertSame(['Plugins\Quote' => $this->root . '/Plugins/Quote'], $cache->psr4Map());
    }

    public function testSkipsMissingDirectoryAndManifest(): void
    {
        mkdir($this->root . '/Plugins/NoManifest', 0775, true);
        $cache = $this->cache();
        $cache->write(['ghost' => 'Ghost', 'no_manifest' => 'NoManifest']);

        $this->assertSame([], $cache->psr4Map());
    }

    public function testWriteReplacesAtomicallyWithoutLeftovers(): void
    {
        $this->makePlugin('Quote');
        $cache = $this->cache();
        $cache->write(['quote' => 'Quote']);
        $cache->write([]);

        $this->assertSame([], $cache->psr4Map());
        $this->assertSame(['active.php'], array_values(array_diff(scandir($this->root . '/state') ?: [], ['.', '..'])));
    }
}
