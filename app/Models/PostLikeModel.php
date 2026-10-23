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
        if ($this->hasLiked($postId, $voterKey)) {
            $this->where('post_id', $postId)->where('voter_key', $voterKey)->delete();

            return false;
        }

        $this->insert(['post_id' => $postId, 'voter_key' => $voterKey]);

        return true;
    }
}
