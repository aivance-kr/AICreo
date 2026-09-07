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
}
