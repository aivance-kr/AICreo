<?php

declare(strict_types=1);

namespace App\Libraries\Plugin;

use App\Exceptions\InvalidManifestException;
use JsonException;

/**
 * plugin.json 매니페스트.
 *
 * 생성 시점에 스키마 전체를 검증한다 — 인스턴스가 존재한다는 것은
 * 매니페스트가 유효하다는 뜻이다.
 */
final readonly class PluginManifest
{
    public const KEY_PATTERN    = '/^[a-z][a-z0-9_]*$/';
    public const SEMVER_PATTERN = '/^\d+\.\d+\.\d+$/';

    /**
     * @param list<string>   $requiresPlugins
     * @param list<MenuItem> $menu
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $version,
        public LicenseType $licenseType,
        public string $directoryName,
        public string $requiresCore = '*',
        public array $requiresPlugins = [],
        public array $menu = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data          plugin.json 을 디코드한 배열
     * @param string               $directoryName app/Plugins/ 아래 실제 디렉터리명
     */
    public static function fromArray(array $data, string $directoryName): self
    {
        $key = $data['key'] ?? null;
        if (! is_string($key) || preg_match(self::KEY_PATTERN, $key) !== 1 || strlen($key) > 32) {
            throw InvalidManifestException::forField('key', '소문자로 시작하는 [a-z0-9_] 32자 이내여야 합니다');
        }

        $expectedDirectory = self::toPascalCase($key);
        if ($directoryName !== $expectedDirectory) {
            throw InvalidManifestException::forField(
                'key',
                "디렉터리명과 어긋납니다 (key '{$key}' → '{$expectedDirectory}' 이어야 하는데 '{$directoryName}')",
            );
        }

        $name = $data['name'] ?? null;
        if (! is_string($name) || $name === '' || mb_strlen($name) > 64) {
            throw InvalidManifestException::forField('name', '1~64자 문자열이어야 합니다');
        }

        $version = $data['version'] ?? null;
        if (! is_string($version) || preg_match(self::SEMVER_PATTERN, $version) !== 1) {
            throw InvalidManifestException::forField('version', 'MAJOR.MINOR.PATCH 형식이어야 합니다');
        }

        $rawLicenseType = $data['license_type'] ?? null;
        $licenseType    = LicenseType::tryFrom(is_string($rawLicenseType) ? $rawLicenseType : '');
        if (! $licenseType instanceof LicenseType) {
            throw InvalidManifestException::forField('license_type', 'onetime | subscription | credit 중 하나여야 합니다');
        }

        $requires        = is_array($data['requires'] ?? null) ? $data['requires'] : [];
        $requiresCore    = is_string($requires['core'] ?? null) ? $requires['core'] : '*';
        $requiresPlugins = [];

        foreach (is_array($requires['plugins'] ?? null) ? $requires['plugins'] : [] as $required) {
            if (! is_string($required) || preg_match(self::KEY_PATTERN, $required) !== 1) {
                throw InvalidManifestException::forField('requires.plugins', '플러그인 key 문자열 배열이어야 합니다');
            }

            $requiresPlugins[] = $required;
        }

        $menu = [];

        foreach (is_array($data['menu'] ?? null) ? $data['menu'] : [] as $item) {
            if (! is_array($item)) {
                throw InvalidManifestException::forField('menu', '객체 배열이어야 합니다');
            }

            $menu[] = MenuItem::fromArray($item);
        }

        return new self($key, $name, $version, $licenseType, $directoryName, $requiresCore, $requiresPlugins, $menu);
    }

    public static function fromJsonFile(string $path): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw InvalidManifestException::forField('plugin.json', "파일을 읽을 수 없습니다: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw InvalidManifestException::forField('plugin.json', "파일을 읽을 수 없습니다: {$path}");
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw InvalidManifestException::forField('plugin.json', 'JSON 파싱 실패: ' . $e->getMessage());
        }

        if (! is_array($data)) {
            throw InvalidManifestException::forField('plugin.json', '최상위가 객체여야 합니다');
        }

        return self::fromArray($data, basename(dirname($path)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key'          => $this->key,
            'name'         => $this->name,
            'version'      => $this->version,
            'license_type' => $this->licenseType->value,
            'requires'     => ['core' => $this->requiresCore, 'plugins' => $this->requiresPlugins],
            'menu'         => array_map(static fn (MenuItem $item): array => $item->toArray(), $this->menu),
        ];
    }

    /**
     * snake_case key 를 디렉터리·네임스페이스용 PascalCase 로 바꾼다.
     * 예: ai_quote → AiQuote
     */
    public static function toPascalCase(string $key): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
    }
}
