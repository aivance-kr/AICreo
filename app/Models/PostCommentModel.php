<?php

namespace App\Models;

use CodeIgniter\Model;

class PostCommentModel extends Model
{
    protected $table          = 'post_comments';
    protected $primaryKey     = 'id';
    protected $useTimestamps  = true;
    protected $updatedField   = '';
    protected $useSoftDeletes = true;
    protected $allowedFields  = [
        'post_id', 'user_id', 'content',
        'author_name', 'author_password', 'is_active', 'ip_address',
        'wp_comment_id', 'created_at',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function getByPost(int $postId): array
    {
        return $this->select('post_comments.*, users.nickname as user_nickname')
            ->join('users', 'users.id = post_comments.user_id', 'left')
            ->where('post_comments.post_id', $postId)
            ->where('post_comments.is_active', 1)
            ->orderBy('post_comments.id', 'ASC')
            ->findAll();
    }

    /**
     * @return array{comments: list<array<string, mixed>>, total: int}
     */
    public function getAdminList(int $page, int $perPage): array
    {
        $offset  = ($page - 1) * $perPage;
        $builder = $this->select('post_comments.*, boards.name AS board_name, boards.slug AS board_slug, posts.title AS post_title, users.nickname AS user_nickname')
            ->join('posts', 'posts.id = post_comments.post_id', 'inner')
            ->join('boards', 'boards.id = posts.board_id', 'inner')
            ->join('users', 'users.id = post_comments.user_id', 'left');

        $total    = (clone $builder)->countAllResults(false);
        $comments = $builder->orderBy('post_comments.id', 'DESC')->findAll($perPage, $offset);

        return ['comments' => $comments, 'total' => $total];
    }

    public function toggleActive(int $id): void
    {
        $comment = $this->find($id);
        if ($comment === null) {
            return;
        }

        $this->update($id, ['is_active' => $comment['is_active'] ? 0 : 1]);
    }

    /**
     * 관리자 대시보드에 표시할 최신 댓글을 게시글·게시판 정보와 함께 반환한다.
     *
     * @return list<array<string, mixed>>
     */
    public function getRecent(int $limit): array
    {
        return $this->select('post_comments.*, posts.title AS post_title, boards.name AS board_name, boards.slug AS board_slug')
            ->join('posts', 'posts.id = post_comments.post_id', 'inner')
            ->join('boards', 'boards.id = posts.board_id', 'inner')
            ->where('posts.deleted_at', null)
            ->orderBy('post_comments.id', 'DESC')
            ->findAll($limit);
    }
}
