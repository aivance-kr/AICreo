<?php

declare(strict_types=1);

namespace App\Libraries\Plugin;

use App\Exceptions\InvalidManifestException;
use App\Exceptions\PluginException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

/**
 * 플러그인 ZIP 설치기.
 *
 * 플러그인은 실행 코드를 담으므로 검증을 전부 통과하기 전에는 목적지에 아무것도 쓰지 않는다.
 * 설치 디렉터리명은 ZIP 의 폴더 구조가 아니라 plugin.json 의 key 에서 유도한다.
 * 설치 직후 상태는 DB 에 등록되기 전(= inactive 가 될 후보)이며, 등록은 호출 측이 sync() 로 한다.
 */
class PluginInstaller
{
    public const MAX_ENTRIES     = 2000;
    public const MAX_TOTAL_BYTES = 50 * 1024 * 1024;

    /**
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = [
        'php', 'json', 'css', 'js', 'map', 'md', 'png', 'jpg', 'jpeg', 'gif',
        'svg', 'webp', 'ico', 'woff', 'woff2', 'ttf',
    ];

    /**
     * @var list<string>
     */
    public const PUBLIC_EXTENSIONS = [
        'css', 'js', 'map', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'woff', 'woff2', 'ttf',
    ];

    private readonly string $pluginsPath;
    private readonly string $publicPath;
    private readonly string $tmpRoot;

    public function __construct(?string $pluginsPath = null, ?string $publicPath = null, ?string $tmpRoot = null)
    {
        $this->pluginsPath = rtrim($pluginsPath ?? APPPATH . 'Plugins', '/\\');
        $this->publicPath  = rtrim($publicPath ?? FCPATH . 'plugins', '/\\');
        $this->tmpRoot     = rtrim($tmpRoot ?? WRITEPATH . 'plugins_tmp', '/\\');
    }

    /**
     * @throws InvalidManifestException|PluginException 검증 실패 시 (목적지는 변경되지 않는다)
     */
    public function install(string $zipPath): PluginManifest
    {
        $zip = new ZipArchive();

        if (! is_file($zipPath) || $zip->open($zipPath) !== true) {
            throw new PluginException('ZIP 파일을 열 수 없습니다.');
        }

        $workDir = $this->tmpRoot . DIRECTORY_SEPARATOR . 'plugin_' . bin2hex(random_bytes(8));

        try {
            $names = $this->validateEntries($zip);

            if (! in_array('plugin.json', $names, true)) {
                throw new PluginException('ZIP 최상위에 plugin.json 이 없습니다.');
            }

            $sourceDir = $workDir . DIRECTORY_SEPARATOR . 'src';
            $this->extract($zip, $names, $sourceDir);

            $manifest = $this->readManifest($sourceDir . DIRECTORY_SEPARATOR . 'plugin.json');
            $this->place($manifest, $sourceDir, $workDir);

            return $manifest;
        } finally {
            $zip->close();
            $this->deleteDirectory($workDir);
        }
    }

    /**
     * 모든 엔트리를 해제 전에 검사한다.
     *
     * @return list<string> 파일 엔트리 이름 (디렉터리 엔트리 제외)
     */
    private function validateEntries(ZipArchive $zip): array
    {
        if ($zip->numFiles > self::MAX_ENTRIES) {
            throw new PluginException('엔트리 수가 너무 많습니다 (최대 ' . self::MAX_ENTRIES . '개).');
        }

        $files = [];
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat === false ? '' : $stat['name'];

            if ($name === '' || $this->isUnsafePath($name)) {
                throw new PluginException("허용되지 않는 경로입니다: {$name}");
            }

            if (str_ends_with($name, '/')) {
                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            // public/ 은 웹 루트로 옮겨지므로 실행·XSS 가능한 형식(php·json·md·svg)은 받지 않는다.
            $allowed = str_starts_with(strtolower($name), 'public/') ? self::PUBLIC_EXTENSIONS : self::ALLOWED_EXTENSIONS;
            if (! in_array($extension, $allowed, true)) {
                throw new PluginException("허용되지 않는 파일 형식입니다: {$name}");
            }

            // 선언 크기는 위조될 수 있어 해제 시 실제 바이트로 다시 제한한다. 여기서는 빠른 거부용.
            $total += (int) ($stat['size'] ?? 0);
            if ($total > self::MAX_TOTAL_BYTES) {
                throw new PluginException('압축 해제 용량이 너무 큽니다 (최대 50MB).');
            }

            $files[] = $name;
        }

        return $files;
    }

    /**
     * Zip-slip: '..' 세그먼트, 선행 '/', 백슬래시, 드라이브 접두어, NUL 바이트.
     */
    private function isUnsafePath(string $name): bool
    {
        if (str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/')) {
            return true;
        }

        if (preg_match('/^[A-Za-z]:/', $name) === 1) {
            return true;
        }

        // '..' 뿐 아니라 '.'·빈 세그먼트('a//b', './public/x')도 거부해 검증과 실제 경로 해석이 어긋나지 않게 한다.
        foreach (explode('/', rtrim($name, '/')) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return true;
            }
        }

        return false;
    }

    /**
     * 엔트리를 스트림으로 복사하며 실제 바이트 수를 센다 (zip bomb 방어).
     *
     * @param list<string> $names
     */
    private function extract(ZipArchive $zip, array $names, string $targetDir): void
    {
        $written = 0;

        foreach ($names as $name) {
            $destination = $targetDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            $this->makeDirectory(dirname($destination));

            $in  = $zip->getStream($name);
            $out = fopen($destination, 'wb');

            if ($in === false || $out === false) {
                throw new PluginException("파일을 해제할 수 없습니다: {$name}");
            }

            while (! feof($in)) {
                $chunk = fread($in, 65536);
                if ($chunk === false) {
                    break;
                }

                $written += strlen($chunk);
                if ($written > self::MAX_TOTAL_BYTES) {
                    fclose($in);
                    fclose($out);

                    throw new PluginException('압축 해제 용량이 너무 큽니다 (최대 50MB).');
                }

                fwrite($out, $chunk);
            }

            fclose($in);
            fclose($out);
        }
    }

    private function readManifest(string $jsonPath): PluginManifest
    {
        $data = json_decode((string) file_get_contents($jsonPath), true);
        $key  = is_array($data) ? ($data['key'] ?? null) : null;

        // 디렉터리명은 ZIP 이 아니라 key 에서 유도하므로 일치 검사는 항상 통과하고, 나머지 스키마만 검증된다.
        return PluginManifest::fromArray(
            is_array($data) ? $data : [],
            is_string($key) ? PluginManifest::toPascalCase($key) : '',
        );
    }

    /**
     * 검증이 끝난 뒤에만 호출한다. 실패하면 기존 설치본을 복원한다.
     */
    private function place(PluginManifest $manifest, string $sourceDir, string $workDir): void
    {
        $pluginDest = $this->pluginsPath . DIRECTORY_SEPARATOR . $manifest->directoryName;
        $publicDest = $this->publicPath . DIRECTORY_SEPARATOR . $manifest->key;
        $publicSrc  = $sourceDir . DIRECTORY_SEPARATOR . 'public';
        $backupDir  = $workDir . DIRECTORY_SEPARATOR . 'backup';

        if (is_dir($publicSrc)) {
            $this->assertPublicTreeSafe($publicSrc);
        }

        $movedBackups = [];
        $placed       = [];

        try {
            foreach ([$pluginDest, $publicDest] as $existing) {
                if (is_dir($existing)) {
                    $this->makeDirectory($backupDir);
                    $backup = $backupDir . DIRECTORY_SEPARATOR . md5($existing);
                    $this->move($existing, $backup);
                    $movedBackups[$existing] = $backup;
                }
            }

            if (is_dir($publicSrc)) {
                $this->makeDirectory(dirname($publicDest));
                $this->move($publicSrc, $publicDest);
                $placed[] = $publicDest;
            }

            $this->makeDirectory(dirname($pluginDest));
            $this->move($sourceDir, $pluginDest);
            $placed[] = $pluginDest;
        } catch (Throwable $e) {
            foreach ($placed as $path) {
                $this->deleteDirectory($path);
            }

            foreach ($movedBackups as $original => $backup) {
                if (! @rename($backup, $original)) {
                    $this->quarantine($backup);
                    log_message('critical', "[PluginInstaller] 기존 설치본 복원 실패, 보관: {$original}");
                }
            }

            throw $e instanceof PluginException ? $e : new PluginException('플러그인 설치에 실패했습니다.', 0, $e);
        }
    }

    /**
     * 웹 루트로 승격하기 직전에 실제 파일시스템 상태를 다시 검증한다 (ZIP 메타데이터 기준 검증의 이중 안전장치).
     */
    private function assertPublicTreeSafe(string $dir): void
    {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

        foreach ($items as $item) {
            $extension = strtolower($item->getExtension());

            if ($item->isLink() || ! in_array($extension, self::PUBLIC_EXTENSIONS, true)) {
                throw new PluginException('public/ 에 허용되지 않는 파일이 있습니다: ' . $item->getFilename());
            }
        }
    }

    /**
     * 복원하지 못한 기존 설치본을 정리 대상(workDir) 밖으로 빼서 보존한다.
     */
    private function quarantine(string $backup): void
    {
        $target = dirname($this->tmpRoot) . DIRECTORY_SEPARATOR . 'plugins_failed' . DIRECTORY_SEPARATOR . date('YmdHis') . '_' . basename($backup);
        $this->makeDirectory(dirname($target));
        @rename($backup, $target);
    }

    private function move(string $from, string $to): void
    {
        if (! @rename($from, $to)) {
            throw new PluginException("디렉터리를 이동할 수 없습니다: {$to}");
        }
    }

    private function makeDirectory(string $path): void
    {
        if (! is_dir($path) && ! @mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new PluginException("디렉터리를 만들 수 없습니다: {$path}");
        }
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir() && ! $item->isLink()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
