<?php

namespace App\Models;

use CodeIgniter\Model;

class BoardCategoryModel extends Model
{
    protected $table         = 'board_categories';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['board_id', 'wp_term_id', 'slug', 'name', 'sort_order', 'is_active'];

    /**
     * 프론트 노출용: 활성 카테고리만 순서대로.
     *
     * @return list<array<string, mixed>>
     */
    public function getByBoard(int $boardId): array
    {
        return $this->where('board_id', $boardId)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->findAll();
    }

    /**
     * 관리자 화면용: 비활성 포함 전체.
     *
     * @return list<array<string, mixed>>
     */
    public function getAllByBoard(int $boardId): array
    {
        return $this->where('board_id', $boardId)->orderBy('sort_order')->findAll();
    }

    /**
     * 게시판에 속한 모든 카테고리의 순서를 드래그앤드롭 결과대로 재기록한다.
     *
     * 다른 게시판의 ID가 섞이거나 누락된 요청은 거부해 게시판 간 순서가 변하지 않게 한다.
     *
     * @param list<int> $ids
     */
    public function reorderByBoard(int $boardId, array $ids): bool
    {
        $categoryIds = array_map('intval', array_column($this->getAllByBoard($boardId), 'id'));

        sort($categoryIds);
        $submittedIds = array_values(array_unique($ids));
        sort($submittedIds);

        if ($submittedIds !== $categoryIds || count($submittedIds) !== count($ids)) {
            return false;
        }

        $this->db->transStart();

        foreach ($ids as $index => $id) {
            $this->update($id, ['sort_order' => $index]);
        }

        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBySlug(int $boardId, string $slug): ?array
    {
        return $this->where('board_id', $boardId)
            ->where('slug', $slug)
            ->where('is_active', 1)
            ->first();
    }
}
