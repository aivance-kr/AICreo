<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PostLikeModel extends Model
{
    protected $table         = 'post_likes';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $allowedFields = ['post_id', 'voter_key'];

    public function countByPost(int $postId): int
    {
        return $this->where('post_id', $postId)->countAllResults();
    }

    public function hasLiked(int $postId, string $voterKey): bool
    {
        return $this->where('post_id', $postId)->where('voter_key', $voterKey)->countAllResults() > 0;
    }

    /**
     * 좋아요를 토글한다.
     *
     * @return bool 토글 후 좋아요 상태 (true = 좋아요 됨)
     */
    public function toggle(int $postId, string $voterKey): bool
    {
        $this->where('post_id', $postId)->where('voter_key', $voterKey)->delete();
        if ($this->db->affectedRows() > 0) {
            return false;
        }

        // 동시 요청이 같은 행을 먼저 넣어도 유니크 키 충돌 없이 넘어간다
        $this->db->table($this->table)->ignore(true)->insert([
            'post_id'    => $postId,
            'voter_key'  => $voterKey,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }
}
