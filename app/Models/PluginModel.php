<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\Plugin\PluginManifest;
use App\Libraries\Plugin\PluginSetting;
use CodeIgniter\Model;

/**
 * 플러그인 레지스트리.
 */
class PluginModel extends Model
{
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_MISSING  = 'missing';

    protected $table         = 'plugins';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    /**
     * @var list<string>
     */
    protected $allowedFields = [
        'plugin_key', 'name', 'version', 'status', 'license_type',
        'price_onetime', 'price_subscription', 'credit_price_table',
        'manifest', 'settings', 'installed_at',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function findByKey(string $key): ?array
    {
        /** @var array<string, mixed>|null $row */
        $row = $this->where('plugin_key', $key)->first();

        return $row;
    }

    /**
     * 스캔 결과를 반영한다. 신규는 inactive 로 등록하고, 기존 레코드는
     * 메타데이터만 갱신한다 — 운영자가 정한 status 와 가격은 건드리지 않는다.
     */
    public function upsertFromManifest(PluginManifest $manifest): void
    {
        // 기존 레코드에 반영할 것은 플러그인 작성자가 정하는 메타데이터뿐이다.
        // license_type 이 여기 없는 것은 의도적이다 — 무엇을 어떻게 팔지는
        // 운영자가 /admin/plugins/{key}/edit 에서 정하고, 매니페스트는 신규
        // 등록 시의 기본값 제안일 뿐이다. 여기에 넣으면 재스캔마다 운영자
        // 설정이 매니페스트 값으로 되돌아간다.
        $payload = [
            'name'     => $manifest->name,
            'version'  => $manifest->version,
            'manifest' => json_encode($manifest->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];

        $existing = $this->findByKey($manifest->key);

        if ($existing === null) {
            $this->insert($payload + [
                'plugin_key'   => $manifest->key,
                'status'       => self::STATUS_INACTIVE,
                'license_type' => $manifest->licenseType->value,
                'installed_at' => date('Y-m-d H:i:s'),
            ]);

            return;
        }

        // missing 이었다가 파일이 돌아왔으면 inactive 로 되돌린다
        if ($existing['status'] === self::STATUS_MISSING) {
            $payload['status'] = self::STATUS_INACTIVE;
        }

        $this->update($existing['id'], $payload);
    }

    /**
     * 파일시스템에서 사라진 플러그인을 missing 으로 표시한다.
     * 레코드를 지우지 않는 이유: 가격·판매 이력이 붙어 있고,
     * 파일이 복구되면 그대로 되살아나야 하기 때문이다.
     *
     * @param list<string> $presentKeys 현재 디렉터리에 존재하는 plugin_key 목록
     */
    public function markMissing(array $presentKeys): void
    {
        $builder = $this->builder()->where('status !=', self::STATUS_MISSING);

        if ($presentKeys !== []) {
            $builder->whereNotIn('plugin_key', $presentKeys);
        }

        $builder->update(['status' => self::STATUS_MISSING, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * 활성 플러그인의 key => 디렉터리명 매핑.
     *
     * @return array<string, string>
     */
    public function activeKeys(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->select('plugin_key')
            ->where('status', self::STATUS_ACTIVE)
            ->orderBy('plugin_key', 'ASC')
            ->findAll();

        $map = [];

        foreach ($rows as $row) {
            $key       = (string) $row['plugin_key'];
            $map[$key] = PluginManifest::toPascalCase($key);
        }

        return $map;
    }

    /**
     * 매니페스트에 선언된 설정 항목.
     *
     * @return list<PluginSetting>
     */
    public function settingDefinitions(string $key): array
    {
        $row = $this->findByKey($key);

        return $row === null ? [] : $this->definitionsOf($row);
    }

    /**
     * 목록 행 하나에서 선언된 설정 항목을 꺼낸다 (추가 쿼리 없음).
     *
     * @param array<string, mixed> $row
     *
     * @return list<PluginSetting>
     */
    public function definitionsOf(array $row): array
    {
        $manifest = is_string($row['manifest'] ?? null) ? json_decode($row['manifest'], true) : null;
        $items    = is_array($manifest) && is_array($manifest['settings'] ?? null) ? $manifest['settings'] : [];

        return array_map(
            static fn (array $item): PluginSetting => PluginSetting::fromArray($item),
            array_values(array_filter($items, 'is_array')),
        );
    }

    /**
     * 설정값 key => 값. 저장된 값이 없는(또는 빈) 항목은 매니페스트 기본값을 쓴다.
     *
     * @return array<string, string>
     */
    public function settingsFor(string $key): array
    {
        $row    = $this->findByKey($key);
        $stored = is_array($row) && is_string($row['settings'] ?? null) ? json_decode($row['settings'], true) : [];
        $stored = is_array($stored) ? $stored : [];
        $values = [];

        foreach ($this->settingDefinitions($key) as $definition) {
            $value                    = $stored[$definition->key] ?? '';
            $values[$definition->key] = is_string($value) && $value !== '' ? $value : $definition->default;
        }

        return $values;
    }

    /**
     * 선언된 항목만 저장한다 (공백 제거, 최대 길이 제한). 선언 밖 키는 무시.
     *
     * @param array<string, mixed> $input
     */
    public function saveSettings(string $key, array $input): void
    {
        $row = $this->findByKey($key);

        if ($row === null) {
            return;
        }

        $values = [];

        foreach ($this->settingDefinitions($key) as $definition) {
            $value                    = $input[$definition->key] ?? '';
            $values[$definition->key] = is_string($value) ? mb_substr(trim($value), 0, PluginSetting::MAX_LENGTH) : '';
        }

        $this->update($row['id'], ['settings' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    }
}
