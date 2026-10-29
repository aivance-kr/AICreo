<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\BoardCategoryModel;
use App\Models\BoardModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class PostModelTest extends DatabaseTestCase
{
    private PostModel $model;
    private int $boardId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model   = new PostModel();
        $this->boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
        $this->userId  = (int) (new UserModel())->insert([
            'username' => 'writer',
            'email'    => 'writer@example.com',
            'password' => password_hash('secret123', PASSWORD_DEFAULT),
            'nickname' => '글쓴이',
            'role'     => 'member',
        ]);
    }

    private function makePost(array $overrides = []): int
    {
        return (int) $this->model->insert(array_merge([
            'board_id'  => $this->boardId,
            'user_id'   => $this->userId,
            'title'     => '제목',
            'content'   => '본문',
            'is_notice' => 0,
        ], $overrides));
    }

    public function testGetListSeparatesNoticesFromPosts(): void
    {
        $this->makePost(['is_notice' => 1, 'title' => '공지글']);
        $this->makePost(['is_notice' => 0, 'title' => '일반글']);

        $result = $this->model->getList($this->boardId, 1, 10);

        $this->assertCount(1, $result['notices']);
        $this->assertCount(1, $result['posts']);
        $this->assertSame('공지글', $result['notices'][0]['title']);
    }

    public function testGetListIncludesLikeAndActiveCommentCounts(): void
    {
        $id = $this->makePost();
        $this->db->table('post_likes')->insertBatch([
            ['post_id' => $id, 'voter_key' => 'a', 'created_at' => date('Y-m-d H:i:s')],
            ['post_id' => $id, 'voter_key' => 'b', 'created_at' => date('Y-m-d H:i:s')],
        ]);
        $this->db->table('post_comments')->insertBatch([
            ['post_id' => $id, 'content' => '댓글1', 'author_name' => '갑', 'is_active' => 1],
            ['post_id' => $id, 'content' => '숨김', 'author_name' => '을', 'is_active' => 0],
        ]);

        $post = $this->model->getList($this->boardId, 1, 10)['posts'][0];

        $this->assertSame(2, (int) $post['like_count']);
        $this->assertSame(1, (int) $post['comment_count']);
    }

    public function testGetTotalCountExcludesNotices(): void
    {
        $this->makePost(['is_notice' => 1]);
        $this->makePost(['is_notice' => 0]);
        $this->makePost(['is_notice' => 0]);

        $this->assertSame(2, $this->model->getTotalCount($this->boardId));
    }

    public function testIncrementViewIncreasesViews(): void
    {
        $id = $this->makePost();
        $this->assertSame(0, (int) $this->model->find($id)['views']);

        $this->model->incrementView($id);

        $this->assertSame(1, (int) $this->model->find($id)['views']);
    }

    public function testSoftDeleteHidesPostFromDefaultQuery(): void
    {
        $id = $this->makePost();
        $this->model->delete($id);

        $this->assertNull($this->model->find($id));
        $this->assertNotNull($this->model->withDeleted()->find($id));
    }

    public function testGetLatestPublicExcludesSecretPosts(): void
    {
        $this->makePost(['title' => '공개 글']);
        $this->makePost(['title' => '비밀 글', 'is_secret' => 1]);

        $posts = $this->model->getLatestPublic(10);

        $this->assertSame(['공개 글'], array_column($posts, 'title'));
        $this->assertSame('notice', $posts[0]['board_slug']);
    }

    public function testGetPreviousReturnsOlderPostInSameBoard(): void
    {
        $older  = $this->makePost(['title' => '이전 글']);
        $middle = $this->makePost(['title' => '현재 글']);
        $this->makePost(['title' => '다음 글']);

        $prev = $this->model->getPrevious($this->boardId, $middle);

        $this->assertNotNull($prev);
        $this->assertSame($older, (int) $prev['id']);
        $this->assertSame('이전 글', $prev['title']);
    }

    public function testGetPreviousReturnsNullWhenNoOlderPost(): void
    {
        $only = $this->makePost(['title' => '유일한 글']);

        $this->assertNull($this->model->getPrevious($this->boardId, $only));
    }

    public function testGetNextReturnsNewerPostInSameBoard(): void
    {
        $this->makePost(['title' => '이전 글']);
        $middle = $this->makePost(['title' => '현재 글']);
        $newer  = $this->makePost(['title' => '다음 글']);

        $next = $this->model->getNext($this->boardId, $middle);

        $this->assertNotNull($next);
        $this->assertSame($newer, (int) $next['id']);
        $this->assertSame('다음 글', $next['title']);
    }

    public function testGetNextReturnsNullWhenNoNewerPost(): void
    {
        $only = $this->makePost(['title' => '유일한 글']);

        $this->assertNull($this->model->getNext($this->boardId, $only));
    }

    public function testGetPreviousAndNextExcludeNoticesAndSecretPosts(): void
    {
        $this->makePost(['title' => '공지', 'is_notice' => 1]);
        $this->makePost(['title' => '비밀 글', 'is_secret' => 1]);
        $current = $this->makePost(['title' => '현재 글']);

        $this->assertNull($this->model->getPrevious($this->boardId, $current));
        $this->assertNull($this->model->getNext($this->boardId, $current));
    }

    public function testGetPreviousAndNextAreScopedToBoard(): void
    {
        $otherBoardId = (int) (new BoardModel())->getBySlug('free')['id'];
        $current      = $this->makePost(['title' => '현재 글']);
        $this->model->insert([
            'board_id' => $otherBoardId,
            'user_id'  => $this->userId,
            'title'    => '다른 게시판 글',
            'content'  => '본문',
        ]);

        $this->assertNull($this->model->getNext($this->boardId, $current));
    }

    public function testGetPreviousAndNextExcludeHiddenPosts(): void
    {
        $this->makePost(['title' => '숨김 이전 글', 'is_active' => 0]);
        $current = $this->makePost(['title' => '현재 글']);
        $this->makePost(['title' => '숨김 다음 글', 'is_active' => 0]);

        $this->assertNull($this->model->getPrevious($this->boardId, $current));
        $this->assertNull($this->model->getNext($this->boardId, $current));
    }

    public function testGetLatestPublicExcludesHiddenPosts(): void
    {
        $this->makePost(['title' => '노출 글']);
        $this->makePost(['title' => '숨김 글', 'is_active' => 0]);

        $posts = $this->model->getLatestPublic(10);

        $this->assertSame(['노출 글'], array_column($posts, 'title'));
    }

    public function testGetListExcludesHiddenPosts(): void
    {
        $this->makePost(['title' => '노출 글']);
        $this->makePost(['title' => '숨김 글', 'is_active' => 0]);

        $result = $this->model->getList($this->boardId, 1, 10);

        $this->assertSame(['노출 글'], array_column($result['posts'], 'title'));
    }

    public function testGetTotalCountExcludesHiddenPosts(): void
    {
        $this->makePost();
        $this->makePost(['is_active' => 0]);

        $this->assertSame(1, $this->model->getTotalCount($this->boardId));
    }

    public function testSearchExcludesHiddenPosts(): void
    {
        $this->makePost(['title' => '검색어 노출']);
        $this->makePost(['title' => '검색어 숨김', 'is_active' => 0]);

        $result = $this->model->search($this->boardId, '검색어', 'title', 1, 10);

        $this->assertSame(['검색어 노출'], array_column($result['posts'], 'title'));
        $this->assertSame(1, $result['total']);
    }

    public function testSearchByTagMatchesExactTagOnly(): void
    {
        $this->makePost(['title' => '일치', 'tags' => 'php,CI4']);
        $this->makePost(['title' => '부분일치', 'tags' => 'php8,ci45']);
        $this->makePost(['title' => '없음']);

        $result = $this->model->search($this->boardId, 'ci4', 'tag', 1, 10);

        $this->assertSame(['일치'], array_column($result['posts'], 'title'));
        $this->assertSame(1, $result['total']);
    }

    public function testGetAdminListIncludesHiddenPosts(): void
    {
        $this->makePost(['title' => '노출 글']);
        $this->makePost(['title' => '숨김 글', 'is_active' => 0]);

        $result = $this->model->getAdminList(1, 10);

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['posts']);
    }

    public function testToggleActiveFlipsState(): void
    {
        $id = $this->makePost();

        $this->model->toggleActive($id);
        $this->assertSame(0, (int) $this->model->find($id)['is_active']);

        $this->model->toggleActive($id);
        $this->assertSame(1, (int) $this->model->find($id)['is_active']);
    }

    public function testSearchPublicMatchesTitleOrContentAndExcludesHidden(): void
    {
        $this->makePost(['title' => '라라벨 입문', 'content' => '본문']);
        $this->makePost(['title' => '기타', 'content' => '라라벨 팁']);
        $this->makePost(['title' => '라라벨 비밀', 'is_secret' => 1]);
        $this->makePost(['title' => '라라벨 숨김', 'is_active' => 0]);
        $this->makePost(['title' => '무관']);

        $result = $this->model->searchPublic('라라벨', 1, 10);

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['posts']);
        $this->assertSame('notice', $result['posts'][0]['board_slug']);
    }

    public function testSearchPublicPaginates(): void
    {
        foreach (range(1, 3) as $n) {
            $this->makePost(['title' => "페이지 글 {$n}"]);
        }

        $result = $this->model->searchPublic('페이지', 2, 2);

        $this->assertSame(3, $result['total']);
        $this->assertCount(1, $result['posts']);
    }

    public function testGetRelatedOrdersByTagOverlapThenNewest(): void
    {
        cache()->clean();
        $current = $this->makePost(['tags' => 'php,ci4,redis']);
        $one     = $this->makePost(['title' => '하나', 'tags' => 'php']);
        $two     = $this->makePost(['title' => '둘', 'tags' => 'php,ci4']);
        $oneNew  = $this->makePost(['title' => '하나최신', 'tags' => 'redis']);
        $this->makePost(['title' => '무관', 'tags' => 'java']);

        $ids = array_column($this->model->getRelated($current), 'id');

        $this->assertSame([$two, $oneNew, $one], $ids);
    }

    public function testGetRelatedExcludesNonPublicAndMatchesCategory(): void
    {
        cache()->clean();
        $categoryId = (int) model(BoardCategoryModel::class)->insert(['board_id' => $this->boardId, 'name' => '분류']);
        $current    = $this->makePost(['tags' => 'php', 'category_id' => $categoryId]);
        $byCategory = $this->makePost(['title' => '같은분류', 'category_id' => $categoryId]);
        $this->makePost(['title' => '비밀', 'tags' => 'php', 'is_secret' => 1]);
        $this->makePost(['title' => '숨김', 'tags' => 'php', 'is_active' => 0]);

        $this->assertSame([$byCategory], array_column($this->model->getRelated($current), 'id'));
    }

    public function testGetRelatedIsEmptyWithoutTagsOrCategory(): void
    {
        cache()->clean();
        $current = $this->makePost();
        $this->makePost(['tags' => 'php']);

        $this->assertSame([], $this->model->getRelated($current));
    }

    public function testGetRelatedCacheIsInvalidatedOnSaveAndDelete(): void
    {
        cache()->clean();
        $current = $this->makePost(['tags' => 'php']);
        $this->assertSame([], $this->model->getRelated($current));

        $other = $this->makePost(['title' => '새글', 'tags' => 'php']);
        $this->assertSame([$other], array_column($this->model->getRelated($current), 'id'));

        $this->model->delete($other);
        $this->assertSame([], $this->model->getRelated($current));
    }
}
