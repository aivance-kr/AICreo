<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class AdModel extends Model
{
    public const POSITIONS = [
        'home_top'    => '홈 상단',
        'home_bottom' => '홈 하단',
        'post_top'    => '게시글 본문 상단',
        'post_bottom' => '게시글 본문 하단',
    ];

    protected $table         = 'ads';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'position', 'code', 'priority', 'is_active'];
    protected $afterInsert   = ['clearCacheCallback'];
    protected $afterUpdate   = ['clearCacheCallback'];
    protected $afterDelete   = ['clearCacheCallback'];

    /**
     * 활성 광고를 위치별로 캐시해 조회한다 (캐시 1시간).
     *
     * @return list<array<string, mixed>>
     */
    public function getActiveByPosition(string $position): array
    {
        $grouped = cache()->remember('active_ads', 3600, function (): array {
            $rows = $this->where('is_active', 1)->orderBy('priority', 'ASC')->findAll();
            $map  = [];

            foreach ($rows as $row) {
                $map[$row['position']][] = $row;
            }

            return $map;
        });

        return $grouped[$position] ?? [];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function clearCacheCallback(array $data): array
    {
        cache()->delete('active_ads');

        return $data;
    }
}
