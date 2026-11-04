<?php

declare(strict_types=1);

namespace App\Libraries\Plugin;

use Closure;
use RuntimeException;

/**
 * 활성 플러그인 목록의 부팅용 투영 파일(`writable/plugins/active.php`).
 *
 * 진실은 DB `plugins` 테이블이고, 부팅(오토로더 이전)에는 DB 가 없으므로 이 파일만 읽는다.
 * 읽기 로직은 오토로더 이전에도 쓰이는 `active_plugins.php` 한 벌만 사용한다.
 */
final class ActivePluginCache
{
    public function __construct(
        private readonly string $cacheFile,
        private readonly string $pluginsDir,
    ) {
    }

    /**
     * 활성 플러그인의 PSR-4 맵(`Plugins\{Name}` => 절대 경로).
     *
     * @return array<string, string>
     */
    public function psr4Map(): array
    {
        /** @var Closure(string, string): array<string, string> $loader */
        $loader = require __DIR__ . '/active_plugins.php';

        return $loader($this->cacheFile, $this->pluginsDir);
    }

    /**
     * 임시 파일에 쓴 뒤 `rename` 으로 원자적으로 교체하고 opcache 를 무효화한다.
     *
     * @param array<string, string> $active plugin_key => 디렉터리명
     */
    public function write(array $active): void
    {
        $dir = dirname($this->cacheFile);

        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('플러그인 캐시 디렉터리를 만들 수 없습니다.');
        }

        $tmp = tempnam($dir, 'active_');

        if ($tmp === false) {
            throw new RuntimeException('플러그인 캐시 임시 파일을 만들 수 없습니다.');
        }

        $code = "<?php\n\nreturn " . var_export($active, true) . ";\n";

        if (file_put_contents($tmp, $code) === false || ! rename($tmp, $this->cacheFile)) {
            if (is_file($tmp)) {
                unlink($tmp);
            }

            throw new RuntimeException('플러그인 캐시를 쓸 수 없습니다.');
        }

        // tempnam 은 0600 으로 만들므로 웹 서버가 읽을 수 있게 읽기 전용(0644)으로 맞춘다
        chmod($this->cacheFile, 0644);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->cacheFile, true);
        }
    }
}
