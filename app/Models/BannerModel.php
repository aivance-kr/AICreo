<?php

namespace App\Models;

use CodeIgniter\Model;

class BannerModel extends Model
{
    public const POSITIONS = [
        'main_top'    => '메인 상단',
        'main_bottom' => '메인 하단',
        'sub_left'    => '서브 좌측',
        'sub_right'   => '서브 우측',
    ];

    protected $table         = 'banners';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'image_path', 'alt_text', 'link_url', 'link_target', 'position',
        'priority', 'is_active', 'started_at', 'ended_at',
    ];
    protected $afterInsert = ['clearCacheCallback'];
    protected $afterUpdate = ['clearCacheCallback'];
    protected $afterDelete = ['clearCacheCallback'];

    /**
     * 활성 배너 전체를 캐시하고 노출 기간만 PHP에서 필터링 (캐시 1시간)
     *
     * @return list<array<string, mixed>>
     */
    public function getActiveByPosition(string $position): array
    {
        $grouped = cache()->remember('active_banners', 3600, function (): array {
            $rows = $this->where('is_active', 1)->orderBy('priority', 'ASC')->findAll();
            $map  = [];

            foreach ($rows as $row) {
                $map[$row['position']][] = $row;
            }

            return $map;
        });

        $now = date('Y-m-d H:i:s');

        return array_values(array_filter(
            $grouped[$position] ?? [],
            static fn (array $b): bool => ($b['started_at'] === null || $b['started_at'] <= $now)
                   && ($b['ended_at'] === null || $b['ended_at'] >= $now),
        ));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function clearCacheCallback(array $data): array
    {
        cache()->delete('active_banners');

        return $data;
    }

    public function deleteWithFile(int $id): bool
    {
        $banner = $this->find($id);
        if (! $banner) {
            return false;
        }

        $fullPath = FCPATH . $banner['image_path'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        return (bool) $this->delete($id);
    }
}
