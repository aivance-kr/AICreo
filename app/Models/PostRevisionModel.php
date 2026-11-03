<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * 글 수정 이력. 수정 저장으로 덮어쓰이기 직전의 제목·본문·메타 설명을 보관한다.
 */
class PostRevisionModel extends Model
{
    /**
     * 글당 보관하는 이력 개수. 넘으면 오래된 것부터 지운다
     */
    public const MAX_PER_POST = 50;

    /**
     * 이력으로 남기는 글 필드
     */
    private const TRACKED_FIELDS = ['title', 'content', 'meta_desc'];

    protected $table         = 'post_revisions';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $allowedFields = ['post_id', 'user_id', 'title', 'content', 'meta_desc'];

    /**
     * 저장하려는 변경에 제목·본문·메타 설명의 실제 차이가 있는지 본다. 변경에 없는 필드는 무시한다.
     *
     * @param array<string, mixed> $post    저장 전 글
     * @param array<string, mixed> $changes 저장하려는 값
     */
    public static function changed(array $post, array $changes): bool
    {
        foreach (self::TRACKED_FIELDS as $field) {
            if (array_key_exists($field, $changes) && (string) $changes[$field] !== (string) ($post[$field] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * 글의 현재 상태를 이력으로 남기고, 글당 상한을 넘은 오래된 이력을 지운다.
     *
     * @param array<string, mixed> $post
     */
    public function record(array $post, ?int $userId): bool
    {
        $postId = (int) $post['id'];

        $saved = $this->insert([
            'post_id'   => $postId,
            'user_id'   => $userId,
            'title'     => (string) $post['title'],
            'content'   => (string) ($post['content'] ?? ''),
            'meta_desc' => $post['meta_desc'] ?? null,
        ]) !== false;

        if ($saved) {
            $this->prune($postId);
        }

        return $saved;
    }

    /**
     * 최신순 이력 목록(본문 제외).
     *
     * @return list<array<string, mixed>>
     */
    public function listByPost(int $postId): array
    {
        return $this->select('post_revisions.id, post_revisions.title, post_revisions.created_at, users.nickname AS user_nickname')
            ->join('users', 'users.id = post_revisions.user_id', 'left')
            ->where('post_revisions.post_id', $postId)
            ->orderBy('post_revisions.id', 'DESC')
            ->findAll();
    }

    /**
     * 글에 속한 이력 한 건. 다른 글의 이력이면 null.
     *
     * @return array<string, mixed>|null
     */
    public function findForPost(int $postId, int $revisionId): ?array
    {
        return $this->where('post_id', $postId)->find($revisionId);
    }

    /**
     * 이력 내용으로 글을 되돌린다. 되돌리기 직전 글은 새 이력으로 남아 다시 되돌릴 수 있다.
     *
     * @return bool 이력이나 글이 없거나 저장에 실패하면 false
     */
    public function restore(int $postId, int $revisionId, ?int $userId): bool
    {
        $revision = $this->findForPost($postId, $revisionId);
        $posts    = new PostModel();
        $post     = $posts->find($postId);

        if ($revision === null || $post === null) {
            return false;
        }

        $this->db->transStart();
        $this->record($post, $userId);
        $posts->update($postId, [
            'title'     => $revision['title'],
            'content'   => $revision['content'],
            'meta_desc' => $revision['meta_desc'],
        ]);
        $this->db->transComplete();

        return $this->db->transStatus();
    }

    private function prune(int $postId): void
    {
        $cutoff = $this->select('id')
            ->where('post_id', $postId)
            ->orderBy('id', 'DESC')
            ->limit(1, self::MAX_PER_POST)
            ->first();

        if ($cutoff !== null) {
            $this->where('post_id', $postId)->where('id <=', $cutoff['id'])->delete();
        }
    }
}
