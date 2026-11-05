<?php

declare(strict_types=1);

namespace Plugins\Bookmark\Models;

use App\Models\Traits\UserScopedModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * 회원별 글 북마크. user_id 스코프는 UserScopedModel 이 강제한다.
 */
class BookmarkItemModel extends Model
{
    use UserScopedModel;

    protected $table         = 'bookmark_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['post_id'];
    protected $useTimestamps = false;

    public function isBookmarked(int $postId): bool
    {
        // countAllResults() 는 beforeFind 스코프를 타지 않으므로 first() 로 조회한다
        return $this->select('id')->where('post_id', $postId)->first() !== null;
    }

    /**
     * 북마크를 토글한다.
     *
     * @return bool 토글 후 북마크 상태 (true = 저장됨)
     */
    public function toggle(int $postId): bool
    {
        $this->where('post_id', $postId)->delete();
        if ($this->db->affectedRows() > 0) {
            return false;
        }

        // 동시 요청이 먼저 넣어도 (user_id, post_id) 유니크 키 충돌 없이 넘어간다
        $this->db->table($this->table)->ignore(true)->insert([
            'user_id'    => $this->requireUserId(),
            'post_id'    => $postId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * 지금 읽을 수 있는 글인지 확인하고 이동 주소 조각을 돌려준다. 없으면 null.
     *
     * @return array{id: int|string, board_slug: string}|null
     */
    public function findVisiblePost(int $postId, string $role): ?array
    {
        /** @var array{id: int|string, board_slug: string}|null */
        return $this->db->table('posts')
            ->select('posts.id, boards.slug AS board_slug')
            ->join('boards', 'boards.id = posts.board_id', 'inner')
            ->where('posts.id', $postId)
            ->groupStart()->where('posts.deleted_at', null)->groupEnd()
            ->where('posts.is_active', 1)
            ->where('posts.is_secret', 0)
            ->where('boards.is_active', 1)
            ->whereIn('boards.read_permission', self::readableLevels($role))
            ->get()
            ->getRowArray();
    }

    /**
     * @return list<array{post_id: int|string, title: string, board_slug: string, board_name: string, created_at: string}>
     */
    public function listVisible(string $role, int $limit, int $offset): array
    {
        /** @var list<array{post_id: int|string, title: string, board_slug: string, board_name: string, created_at: string}> */
        return $this->visible($role)
            ->select('bookmark_items.post_id, posts.title, boards.slug AS board_slug, boards.name AS board_name, bookmark_items.created_at')
            ->where('bookmark_items.user_id', $this->requireUserId())
            ->orderBy('bookmark_items.id', 'DESC')
            ->get($limit, $offset)
            ->getResultArray();
    }

    public function countVisible(string $role): int
    {
        return $this->visible($role)
            ->where('bookmark_items.user_id', $this->requireUserId())
            ->countAllResults();
    }

    /**
     * 글이 지워졌거나 숨겨졌거나 읽기 권한이 없으면 목록에서 제외한다 (행은 남겨 복원 시 다시 보인다).
     */
    private function visible(string $role): BaseBuilder
    {
        return $this->db->table($this->table)
            ->join('posts', 'posts.id = bookmark_items.post_id', 'inner')
            ->join('boards', 'boards.id = posts.board_id', 'inner')
            ->where('posts.deleted_at', null)
            ->where('posts.is_active', 1)
            ->where('posts.is_secret', 0)
            ->where('boards.is_active', 1)
            ->whereIn('boards.read_permission', self::readableLevels($role));
    }

    /**
     * @return list<string>
     */
    private static function readableLevels(string $role): array
    {
        return $role === 'admin' ? ['guest', 'member', 'admin'] : ['guest', 'member'];
    }
}
