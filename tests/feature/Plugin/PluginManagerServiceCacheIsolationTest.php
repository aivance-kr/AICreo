<?php

declare(strict_types=1);

namespace Tests\Feature\Plugin;

use Tests\Support\DatabaseTestCase;

/**
 * service('pluginManager', ...) 가 테스트 환경에서 실 dev 캐시
 * (writable/plugins/active.php)를 건드리지 않는지 검증한다.
 *
 * 회귀 배경: 여러 플러그인 테스트가 service('pluginManager', false)로
 * activate()/deactivate()/sync()를 호출하는데, 팩토리가 항상 기본 캐시
 * 경로(dev 와 동일한 writable/plugins/active.php)를 써서 테스트 스위트를
 * 한 번 돌리고 나면 로컬 php spark serve 의 플러그인 라우트가 전부
 * 사라졌다(마지막에 실행된 테스트의 tearDown 상태가 실 캐시에 남았기 때문).
 *
 * @internal
 */
final class PluginManagerServiceCacheIsolationTest extends DatabaseTestCase
{
    private string $realCacheFile;
    private string $testCacheFile;
    private ?string $realCacheBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->realCacheFile = WRITEPATH . 'plugins/active.php';
        $this->testCacheFile = WRITEPATH . 'plugins/active_test.php';

        if (is_file($this->realCacheFile)) {
            $this->realCacheBackup = file_get_contents($this->realCacheFile);
        }

        if (is_file($this->testCacheFile)) {
            unlink($this->testCacheFile);
        }
    }

    protected function tearDown(): void
    {
        if (is_file($this->testCacheFile)) {
            unlink($this->testCacheFile);
        }

        // 이 테스트가 실 캐시를 건드리지 않는지가 검증 대상이지만, 혹시라도
        // 회귀가 나서 실제로 덮어썼다면 다음 테스트·개발 서버에 영향이
        // 번지지 않도록 원상복구한다.
        if ($this->realCacheBackup !== null) {
            file_put_contents($this->realCacheFile, $this->realCacheBackup);
        }

        parent::tearDown();
    }

    public function testNonSharedInstanceWritesToIsolatedCacheNotRealDevCache(): void
    {
        service('pluginManager', false)->sync();

        $this->assertSame(
            $this->realCacheBackup,
            is_file($this->realCacheFile) ? file_get_contents($this->realCacheFile) : null,
            'service(\'pluginManager\', false) 호출이 실 dev 캐시(writable/plugins/active.php)를 건드리면 안 된다',
        );
        $this->assertFileExists(
            $this->testCacheFile,
            '테스트 환경에서는 격리된 캐시 파일(writable/plugins/active_test.php)에 대신 써야 한다',
        );
    }

    public function testSharedInstanceAlsoWritesToIsolatedCache(): void
    {
        service('pluginManager')->writeActiveCache();

        $this->assertSame(
            $this->realCacheBackup,
            is_file($this->realCacheFile) ? file_get_contents($this->realCacheFile) : null,
            'service(\'pluginManager\')(shared)도 실 dev 캐시를 건드리면 안 된다',
        );
        $this->assertFileExists($this->testCacheFile);
    }
}
