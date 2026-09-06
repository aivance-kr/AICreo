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
