<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class SignatureModel extends Model
{
    protected $table         = 'signatures';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['user_id', 'name', 'content', 'is_selected'];
    protected $useTimestamps = true;

    /**
     * @return list<array<string, mixed>>
     */
    public function listByUser(int $userId): array
    {
        return $this->where('user_id', $userId)->orderBy('id')->findAll();
    }

    /**
     * 사용자의 현재 선택 서명 (없으면 null)
     *
     * @return array<string, mixed>|null
     */
    public function selectedByUser(int $userId): ?array
    {
        return $this->where(['user_id' => $userId, 'is_selected' => 1])->first();
    }

    /**
     * 사용자의 서명 하나만 선택 상태로 만든다
     */
    public function choose(int $userId, int $id): void
    {
        $this->db->transStart();
        $this->where('user_id', $userId)->set('is_selected', 0)->update();
        $this->where(['user_id' => $userId, 'id' => $id])->set('is_selected', 1)->update();
        $this->db->transComplete();
    }

    /**
     * 사용자의 첫 번째(가장 오래된) 서명 — 일괄 삽입 커맨드용
     *
     * @return array<string, mixed>|null
     */
    public function firstByUser(int $userId): ?array
    {
        return $this->where('user_id', $userId)->orderBy('id')->first();
    }
}
