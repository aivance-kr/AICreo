<?php

declare(strict_types=1);

/**
 * 활성 플러그인 PSR-4 맵 로더.
 *
 * `Config\Autoload` 는 오토로더 생성 이전에 인스턴스화되므로 클래스가 아니라
 * 클로저를 돌려주는 평범한 파일로 둔다(`require` 로 바로 읽는다).
 * 런타임의 `ActivePluginCache` 와 테스트가 같은 파일을 써서 로직을 한 벌로 유지한다.
 *
 * 캐시 파일 형식: `return ['quote' => 'Quote', ...];` (plugin_key => 디렉터리명)
 * 파일이 없거나 깨졌거나 배열이 아니면 빈 배열(전부 비활성)을 돌려준다.
 *
 * @return Closure(string, string): array<string, string> 캐시 파일·플러그인 디렉터리 → `Plugins\{Name}` => 경로
 */
return static function (string $cacheFile, string $pluginsDir): array {
    if (! is_file($cacheFile)) {
        return [];
    }

    try {
        $active = (static fn (): mixed => include $cacheFile)();
    } catch (Throwable) {
        return [];
    }

    if (! is_array($active)) {
        return [];
    }

    $base = rtrim($pluginsDir, '/\\');
    $map  = [];

    foreach ($active as $name) {
        // 캐시 변조 시 경로 탈출(`../`) 방지: PascalCase 영숫자만 허용
        if (! is_string($name) || preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) !== 1) {
            continue;
        }

        $dir = $base . '/' . $name;

        if (! is_dir($dir) || ! is_file($dir . '/plugin.json')) {
            continue;
        }

        $map['Plugins\\' . $name] = $dir;
    }

    return $map;
};
