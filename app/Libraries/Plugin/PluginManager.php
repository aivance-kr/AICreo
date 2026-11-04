<?php

declare(strict_types=1);

namespace App\Libraries\Plugin;

use App\Exceptions\InvalidManifestException;
use App\Exceptions\PluginException;
use App\Models\PluginModel;
use CodeIgniter\Database\MigrationRunner;
use CodeIgniter\Router\RouteCollection;
use DirectoryIterator;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * 플러그인 레지스트리 조작의 단일 진입점.
 *
 * 경로를 생성자로 받는 이유는 테스트에서 임시 디렉터리로 갈아끼우기 위해서다.
 * 운영에서는 기본값(writable/plugins/active.php, app/Plugins/)을 쓴다.
 */
class PluginManager
{
    private readonly ActivePluginCache $cache;
    private readonly string $pluginsPath;

    public function __construct(?string $cacheFile = null, ?string $pluginsPath = null)
    {
        $this->pluginsPath = rtrim($pluginsPath ?? APPPATH . 'Plugins', '/\\') . DIRECTORY_SEPARATOR;
        $this->cache       = new ActivePluginCache($cacheFile ?? WRITEPATH . 'plugins/active.php', $this->pluginsPath);
    }

    public function pluginPath(string $directoryName): string
    {
        return $this->pluginsPath . $directoryName;
    }

    public function namespaceFor(string $directoryName): string
    {
        return 'Plugins\\' . $directoryName;
    }

    /**
     * 캐시에 적힌 것 중 실제로 존재하는 플러그인의 디렉터리명.
     *
     * @return list<string> PascalCase 디렉터리명
     */
    public function activeDirectories(): array
    {
        return array_map(
            static fn (string $namespace): string => substr($namespace, strlen('Plugins\\')),
            array_keys($this->cache->psr4Map()),
        );
    }

    /**
     * 활성 플러그인의 Routes.php 를 수집한다.
     *
     * 코어 Routes.php 의 catch-all '(:segment)' 직전에 호출해야 한다 —
     * 그 뒤에 등록하면 모든 플러그인 라우트가 동적 페이지에 가려진다.
     */
    public function loadRoutes(RouteCollection $routes): void
    {
        foreach ($this->activeDirectories() as $directory) {
            $file = $this->pluginPath($directory) . DIRECTORY_SEPARATOR . 'Routes.php';

            if (is_file($file)) {
                require $file;
            }
        }
    }

    /**
     * 활성 플러그인의 Events.php 를 수집한다.
     */
    public function loadEvents(): void
    {
        foreach ($this->activeDirectories() as $directory) {
            $file = $this->pluginPath($directory) . DIRECTORY_SEPARATOR . 'Events.php';

            if (is_file($file)) {
                require $file;
            }
        }
    }

    /**
     * app/Plugins/{*}/plugin.json 을 읽어 유효한 매니페스트만 반환한다.
     *
     * 깨진 매니페스트는 로그만 남기고 건너뛴다 — 하나가 잘못돼도
     * 나머지 플러그인의 운영을 막지 않는다.
     *
     * @return list<PluginManifest>
     */
    public function scan(): array
    {
        if (! is_dir($this->pluginsPath)) {
            return [];
        }

        $manifests = [];

        foreach (new DirectoryIterator($this->pluginsPath) as $entry) {
            if ($entry->isDot() || ! $entry->isDir()) {
                continue;
            }

            $manifestFile = $entry->getPathname() . DIRECTORY_SEPARATOR . 'plugin.json';

            if (! is_file($manifestFile)) {
                continue;
            }

            try {
                $manifests[] = PluginManifest::fromJsonFile($manifestFile);
            } catch (InvalidManifestException $e) {
                log_message('error', '플러그인 매니페스트를 건너뜁니다 [{dir}]: {msg}', [
                    'dir' => $entry->getFilename(),
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        usort($manifests, static fn (PluginManifest $a, PluginManifest $b): int => strcmp($a->key, $b->key));

        return $manifests;
    }

    /**
     * 스캔 결과를 DB 에 반영한다. 신규는 inactive, 사라진 것은 missing.
     */
    public function sync(): void
    {
        $manifests = $this->scan();
        $model     = $this->model();

        foreach ($manifests as $manifest) {
            $model->upsertFromManifest($manifest);
        }

        $model->markMissing(array_map(static fn (PluginManifest $m): string => $m->key, $manifests));

        $this->writeActiveCache();
    }

    /**
     * 활성화 — 마이그레이션 실행 후 status 를 active 로 올리고 캐시를 갱신한다.
     */
    public function activate(string $key): void
    {
        $model = $this->model();
        $row   = $model->findByKey($key);

        if ($row === null) {
            throw new PluginException("등록되지 않은 플러그인입니다: {$key}");
        }

        if ($row['status'] === PluginModel::STATUS_MISSING) {
            throw new PluginException("파일이 없는 플러그인은 활성화할 수 없습니다: {$key}");
        }

        $directory = PluginManifest::toPascalCase($key);

        try {
            $this->runMigrations($directory);
            $model->update($row['id'], ['status' => PluginModel::STATUS_ACTIVE]);
        } catch (Throwable $e) {
            throw new PluginException("플러그인 활성화에 실패했습니다: {$key} — " . $e->getMessage(), 0, $e);
        }

        $this->writeActiveCache();
    }

    /**
     * 비활성화 — 라우트·이벤트만 내린다. 마이그레이션은 되돌리지 않는다.
     * 사용자 데이터를 인질로 잡지 않기 위해서다(문서 §2.5).
     */
    public function deactivate(string $key): void
    {
        $model = $this->model();
        $row   = $model->findByKey($key);

        if ($row === null) {
            throw new PluginException("등록되지 않은 플러그인입니다: {$key}");
        }

        $model->update($row['id'], ['status' => PluginModel::STATUS_INACTIVE]);
        $this->writeActiveCache();
    }

    /**
     * 제거 — 마이그레이션 rollback → 레코드 삭제 → 디렉터리 삭제.
     * 데이터 파괴가 일어나는 유일한 경로다.
     */
    public function uninstall(string $key): void
    {
        $model = $this->model();
        $row   = $model->findByKey($key);

        if ($row === null) {
            throw new PluginException("등록되지 않은 플러그인입니다: {$key}");
        }

        $directory = PluginManifest::toPascalCase($key);
        $path      = $this->pluginPath($directory);

        $this->rollbackMigrations($directory);
        $model->delete($row['id']);

        if (is_dir($path)) {
            $this->deleteDirectory($path);
        }

        $this->writeActiveCache();
    }

    /**
     * DB 의 active 목록을 캐시 파일에 투영한다.
     */
    public function writeActiveCache(): void
    {
        $this->cache->write($this->model()->activeKeys());
    }

    private function model(): PluginModel
    {
        return model(PluginModel::class);
    }

    /**
     * 플러그인 네임스페이스의 마이그레이션만 최신까지 실행한다.
     */
    private function runMigrations(string $directoryName): void
    {
        if (! is_dir($this->pluginPath($directoryName) . '/Database/Migrations')) {
            return;
        }

        $namespace = $this->registerNamespace($directoryName);

        /** @var MigrationRunner $runner */
        $runner = service('migrations');
        $runner->setNamespace($namespace)->latest();
    }

    /**
     * 플러그인 네임스페이스를 오토로더에 등록한다.
     *
     * 활성화·제거 요청이 들어온 요청에서는 부팅 시점 캐시에 없었으므로 아직
     * 등록돼 있지 않다. 마이그레이션 러너가 네임스페이스로 디렉터리를 찾으므로
     * 여기서 런타임 등록을 해준다.
     *
     * 이미 등록돼 있으면 다시 등록하지 않는다 — 중복 등록하면 같은 마이그레이션
     * 파일이 두 번 조회되고, 되돌리기에 쓰는 force() 는 토글이라 두 번째 호출이
     * 방금 되돌린 것을 다시 실행해버린다.
     *
     * @return string 등록된 네임스페이스
     */
    private function registerNamespace(string $directoryName): string
    {
        $namespace  = $this->namespaceFor($directoryName);
        $autoloader = service('autoloader');

        if (! array_key_exists($namespace, $autoloader->getNamespace())) {
            $autoloader->addNamespace($namespace, $this->pluginPath($directoryName));
        }

        return $namespace;
    }

    /**
     * 플러그인 네임스페이스의 마이그레이션만 되돌린다.
     *
     * MigrationRunner::regress() 를 쓰지 않는 이유가 중요하다 —
     * regress() 는 내부에서 namespace 를 null 로 되돌린 뒤 전체 마이그레이션을
     * 배치 단위로 훑는다. setNamespace() 로 좁혀놔도 무시되어, 플러그인 하나를
     * 제거하면 코어를 포함한 **모든 테이블이 삭제된다**.
     *
     * 대신 force() 를 쓴다. force() 는 파일 하나를 대상으로, 이력에 있으면
     * down() 을 실행하고 그 이력 행만 지운다. 역순으로 돌려 의존 순서를 지킨다.
     */
    private function rollbackMigrations(string $directoryName): void
    {
        if (! is_dir($this->pluginPath($directoryName) . '/Database/Migrations')) {
            return;
        }

        $namespace = $this->registerNamespace($directoryName);

        /** @var MigrationRunner $runner */
        $runner = service('migrations');
        $runner->setNamespace($namespace);

        // uid 로 중복을 제거한다 — force() 는 토글이라 같은 파일을 두 번 부르면
        // 되돌린 마이그레이션이 그대로 다시 실행된다.
        $migrations = [];

        foreach ($runner->findNamespaceMigrations($namespace) as $migration) {
            $migrations[(string) $migration->uid] = $migration;
        }

        // 나중 버전부터 되돌려 의존 순서를 지킨다
        krsort($migrations);

        foreach ($migrations as $migration) {
            $runner->force((string) $migration->path, $namespace);
        }
    }

    private function deleteDirectory(string $path): void
    {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
