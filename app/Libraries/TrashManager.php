<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\PageModel;
use App\Models\PostCommentModel;
use App\Models\PostFileModel;
use App\Models\PostModel;
use CodeIgniter\Model;
use Config\Database;
use InvalidArgumentException;

/**
 * 휴지통(소프트 삭제된 글·댓글·페이지) 조회·복원·영구 삭제·기간 만료 비우기.
 *
 * 소프트 삭제는 deleted_at 만 채우므로 첨부 파일·시리즈 소속은 그대로 남고, 복원은 deleted_at 만 비운다.
 * 첨부 파일과 부속 행은 영구 삭제할 때에만 지운다.
 */
final class TrashManager
{
    public const TYPE_POSTS    = 'posts';
    public const TYPE_COMMENTS = 'comments';
    public const TYPE_PAGES    = 'pages';

    /**
     * 유형 키 → 화면 표시명
     */
    public const TYPES = [
        self::TYPE_POSTS    => '글',
        self::TYPE_COMMENTS => '댓글',
        self::TYPE_PAGES    => '페이지',
    ];

    /**
     * 휴지통에 머무는 기본 보관 일수 — 지나면 trash:empty 가 영구 삭제한다
     */
    public const RETENTION_DAYS = 30;

    /**
     * 유형 키 → 테이블명 (쿼리의 deleted_at·id 한정용)
     */
    private const TABLES = [
        self::TYPE_POSTS    => 'posts',
        self::TYPE_COMMENTS => 'post_comments',
        self::TYPE_PAGES    => 'pages',
    ];

    public static function isValidType(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /**
     * 유형별 휴지통 항목 수.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach (array_keys(self::TYPES) as $type) {
            $counts[$type] = $this->model($type)->onlyDeleted()->countAllResults();
        }

        return $counts;
    }

    /**
     * 삭제 시각 최신순 목록. 유형 공통 모양(id·title·meta·deleted_at)으로 맞춰 돌려준다.
     *
     * @return array{items: list<array{id:int,title:string,meta:string,deleted_at:string}>, total:int}
     */
    public function list(string $type, int $page, int $perPage): array
    {
        $table = self::TABLES[$type];
        $total = $this->model($type)->onlyDeleted()->countAllResults();

        $builder = $this->model($type)->onlyDeleted();
        $builder = match ($type) {
            self::TYPE_POSTS => $builder->select('posts.id, posts.title, posts.deleted_at, boards.name AS meta')
                ->join('boards', 'boards.id = posts.board_id', 'left'),
            self::TYPE_COMMENTS => $builder->select('post_comments.id, post_comments.content AS title, post_comments.deleted_at, posts.title AS meta')
                ->join('posts', 'posts.id = post_comments.post_id', 'left'),
            default => $builder->select('pages.id, pages.title, pages.deleted_at, pages.slug AS meta'),
        };

        $rows = $builder->orderBy($table . '.deleted_at', 'DESC')->orderBy($table . '.id', 'DESC')
            ->findAll($perPage, ($page - 1) * $perPage);

        $items = [];

        foreach ($rows as $row) {
            $title = trim(strip_tags((string) $row['title']));
            $meta  = (string) ($row['meta'] ?? '');

            $items[] = [
                'id'    => (int) $row['id'],
                'title' => $type === self::TYPE_COMMENTS ? mb_strimwidth($title, 0, 80, '…') : $title,
                'meta'  => match ($type) {
                    self::TYPE_COMMENTS => $meta !== '' ? '글: ' . $meta : '',
                    self::TYPE_PAGES    => '/' . $meta,
                    default             => $meta,
                },
                'deleted_at' => (string) $row['deleted_at'],
            ];
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * 휴지통에서 꺼낸다(deleted_at 비우기). 모델 훅이 돌아 sitemap·RSS·시리즈 캐시도 갱신된다.
     */
    public function restore(string $type, int $id): bool
    {
        if (! $this->isTrashed($type, $id)) {
            return false;
        }

        return (bool) $this->model($type)->protect(false)->update($id, ['deleted_at' => null]);
    }

    /**
     * 영구 삭제. 글은 첨부 파일(디스크 포함)·댓글·공감·조회 기록·시리즈 소속까지 함께 지운다.
     */
    public function purge(string $type, int $id): bool
    {
        if (! $this->isTrashed($type, $id)) {
            return false;
        }

        if ($type === self::TYPE_POSTS) {
            $this->purgePostDependents($id);
        }

        $this->model($type)->delete($id, true);

        if ($type === self::TYPE_POSTS) {
            Database::connect()->table('post_series')->where('post_id', $id)->delete();
        }

        return true;
    }

    /**
     * 삭제 후 $days 일이 지난 항목을 모두 영구 삭제한다.
     *
     * @return array<string, int> 유형별 삭제 건수
     */
    public function purgeExpired(int $days = self::RETENTION_DAYS): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $purged = [];

        foreach (array_keys(self::TYPES) as $type) {
            $table = self::TABLES[$type];
            $ids   = array_map('intval', $this->model($type)->onlyDeleted()->where($table . '.deleted_at <', $cutoff)->findColumn('id') ?? []);

            $purged[$type] = 0;

            foreach ($ids as $id) {
                // 글 영구 삭제가 딸린 댓글을 먼저 지웠을 수 있어 결과를 그대로 센다
                if ($this->purge($type, $id)) {
                    $purged[$type]++;
                }
            }
        }

        return $purged;
    }

    private function isTrashed(string $type, int $id): bool
    {
        return $this->model($type)->onlyDeleted()->where(self::TABLES[$type] . '.id', $id)->countAllResults() > 0;
    }

    private function purgePostDependents(int $postId): void
    {
        (new PostFileModel())->deleteByPost($postId);

        $db = Database::connect();
        $db->table('post_comments')->where('post_id', $postId)->delete();
        $db->table('post_likes')->where('post_id', $postId)->delete();
        $db->table('post_views')->where('post_id', $postId)->delete();
    }

    private function model(string $type): Model
    {
        return match ($type) {
            self::TYPE_POSTS    => new PostModel(),
            self::TYPE_COMMENTS => new PostCommentModel(),
            self::TYPE_PAGES    => new PageModel(),
            default             => throw new InvalidArgumentException("알 수 없는 휴지통 유형: {$type}"),
        };
    }
}
