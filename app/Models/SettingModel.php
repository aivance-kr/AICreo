<?php

namespace App\Models;

use App\Libraries\Seo\LocaleCache;
use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table         = 'settings';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['group', 'key', 'value', 'label', 'type', 'updated_at'];

    /**
     * 전체 설정을 ['key' => 'value'] 형태로 반환 (캐시 1시간)
     *
     * @return array<string, mixed>
     */
    public function getAllAsMap(): array
    {
        return cache()->remember('site_settings', 3600, function (): array {
            $rows = $this->findAll();
            $map  = [];

            foreach ($rows as $row) {
                $map[$row['key']] = $row['value'];
            }

            return $map;
        });
    }

    /**
     * 특정 그룹의 설정 목록 반환
     *
     * @return list<array<string, mixed>>
     */
    public function getGroup(string $group): array
    {
        return $this->where('group', $group)->orderBy('id')->findAll();
    }

    /**
     * 설정 저장 (키가 있으면 UPDATE, 없으면 INSERT)
     *
     * @param array<string, mixed> $data
     */
    public function saveSettings(array $data): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ($data as $key => $value) {
            $existing = $this->where('key', $key)->first();
            if ($existing) {
                $this->update($existing['id'], ['value' => $value, 'updated_at' => $now]);
            } else {
                // 마이그레이션 없이 새 키가 들어올 경우 INSERT
                $this->insert(['key' => $key, 'value' => $value, 'group' => 'general', 'label' => $key, 'type' => 'text', 'updated_at' => $now]);
            }
        }
        cache()->delete('site_settings');
        // robots.txt·llms.txt 는 설정(ai_crawlers_allow·사이트명·연락처)에 의존하므로 함께 무효화
        cache()->delete('seo_robots');
        LocaleCache::clearLlms();
    }
}
