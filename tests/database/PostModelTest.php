<?php

declare(strict_types=1);

namespace Tests\Database;

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
}
