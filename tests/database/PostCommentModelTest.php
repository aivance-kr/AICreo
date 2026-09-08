<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\BoardModel;
use App\Models\PostCommentModel;
use App\Models\PostModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class PostCommentModelTest extends DatabaseTestCase
{
    private PostCommentModel $commentModel;
    private int $postId;

    protected function setUp(): void
    {
        parent::setUp();

        $boardId      = (int) (new BoardModel())->getBySlug('notice')['id'];
        $this->postId = (int) (new PostModel())->insert([
            'board_id'    => $boardId,
            'title'       => '댓글 대상 게시글',
            'content'     => '본문',
            'author_name' => '글쓴이',
        ]);
        $this->commentModel = new PostCommentModel();
    }

    private function makeComment(array $overrides = []): int
    {
        return (int) $this->commentModel->insert(array_merge([
            'post_id'     => $this->postId,
            'content'     => '댓글 내용',
            'author_name' => '댓글 작성자',
        ], $overrides));
    }

    public function testGetByPostExcludesHiddenComments(): void
    {
        $this->makeComment(['content' => '노출 댓글']);
        $this->makeComment(['content' => '숨김 댓글', 'is_active' => 0]);

        $comments = $this->commentModel->getByPost($this->postId);

        $this->assertSame(['노출 댓글'], array_column($comments, 'content'));
    }

    public function testGetAdminListIncludesHiddenCommentsWithPostContext(): void
    {
        $this->makeComment(['content' => '노출 댓글']);
        $this->makeComment(['content' => '숨김 댓글', 'is_active' => 0]);

        $result = $this->commentModel->getAdminList(1, 20);

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['comments']);
        $this->assertSame('공지사항', $result['comments'][0]['board_name']);
        $this->assertSame('댓글 대상 게시글', $result['comments'][0]['post_title']);
    }

    public function testToggleActiveFlipsState(): void
    {
        $id = $this->makeComment();

        $this->commentModel->toggleActive($id);
        $this->assertSame(0, (int) $this->commentModel->find($id)['is_active']);

        $this->commentModel->toggleActive($id);
        $this->assertSame(1, (int) $this->commentModel->find($id)['is_active']);
    }
}
