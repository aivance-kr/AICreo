<?php

namespace App\Models;

use CodeIgniter\Model;

class MenuModel extends Model
{
    protected $table         = 'menus';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['parent_id', 'title', 'url', 'target', 'sort_order', 'is_active'];

    /**
     * 트리 구조로 메뉴 반환 (캐시 1시간)
     *
     * @return list<array<string, mixed>>
     */
    public function getTree(): array
    {
        return cache()->remember('nav_menus', 3600, fn (): array => $this->buildTree(
            $this->where('is_active', 1)->orderBy('sort_order')->findAll(),
        ));
    }

    /**
     * 관리자 화면용: 비활성 포함 전체 트리 (캐시 없음)
     *
     * @return list<array<string, mixed>>
     */
    public function getTreeAll(): array
    {
        return $this->buildTree($this->orderBy('sort_order')->findAll());
    }

    /**
     * 드래그앤드롭 결과대로 sort_order 를 배열 순서로 재기록
     *
     * @param list<int> $ids
     */
    public function reorder(array $ids): void
    {
        $this->db->transStart();

        foreach ($ids as $index => $id) {
            $this->update($id, ['sort_order' => $index]);
        }
        $this->db->transComplete();
        $this->clearCache();
    }

    public function clearCache(): void
    {
        cache()->delete('nav_menus');
    }

    /**
     * @param list<array<string, mixed>> $rows sort_order 순으로 정렬된 행
     *
     * @return list<array<string, mixed>>
     */
    private function buildTree(array $rows): array
    {
        $tree = [];
        $map  = [];

        foreach ($rows as $item) {
            $item['children'] = [];
            $map[$item['id']] = $item;
        }

        // 참조로 연결해야 부모를 트리에 넣은 뒤에도 자식 추가가 반영된다.
        foreach ($map as $id => $item) {
            if ($item['parent_id'] && isset($map[$item['parent_id']])) {
                $map[$item['parent_id']]['children'][] = &$map[$id];
            } else {
                $tree[] = &$map[$id];
            }
        }
        unset($item);

        return $tree;
    }
}
