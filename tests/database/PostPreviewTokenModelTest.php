<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\BoardModel;
use App\Models\PostModel;
use App\Models\PostPreviewTokenModel;
use InvalidArgumentException;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class PostPreviewTokenModelTest extends DatabaseTestCase
{
    private PostPreviewTokenModel $model;
    private int $postId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model  = new PostPreviewTokenModel();
        $this->postId = (int) (new PostModel())->insert([
            'board_id' => (int) (new BoardModel())->getBySlug('notice')['id'],
            'title'    => '비공개 글', 'content' => '<p>본문</p>', 'author_name' => '관리자', 'is_active' => 0,
        ]);
    }

    public function testIssueReturnsLongRandomTokenThatResolvesToPost(): void
    {
        $token = $this->model->issue($this->postId, 7);

        $this->assertSame(64, strlen($token));
        $this->assertSame($this->postId, (int) $this->model->findValid($token)['id']);
    }

    public function testIssueRejectsNonPositiveDays(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->model->issue($this->postId, 0);
    }

    public function testUnknownTokenIsNotValid(): void
    {
        $this->assertNull($this->model->findValid(str_repeat('a', 64)));
    }

    public function testExpiredTokenIsNotValid(): void
    {
        $token = $this->model->issue($this->postId, 7);
        $this->model->where('token', $token)->set('expires_at', date('Y-m-d H:i:s', time() - 60))->update();

        $this->assertNull($this->model->findValid($token));
    }

    public function testRevokedTokenIsNotValid(): void
    {
        $token = $this->model->issue($this->postId, 7);
        $id    = (int) $this->model->listByPost($this->postId)[0]['id'];

        $this->assertTrue($this->model->revoke($this->postId, $id));
        $this->assertNull($this->model->findValid($token));
    }

    public function testRevokeIgnoresOtherPostsToken(): void
    {
        $this->model->issue($this->postId, 7);
        $id = (int) $this->model->listByPost($this->postId)[0]['id'];

        $this->assertFalse($this->model->revoke($this->postId + 999, $id));
        $this->assertNotNull($this->model->findValid($this->model->listByPost($this->postId)[0]['token']));
    }

    public function testDeletedPostTokenIsNotValid(): void
    {
        $token = $this->model->issue($this->postId, 7);
        (new PostModel())->delete($this->postId);

        $this->assertNull($this->model->findValid($token));
    }
}
