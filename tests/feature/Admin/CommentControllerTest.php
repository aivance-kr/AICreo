<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BoardModel;
use App\Models\PostCommentModel;
use App\Models\PostModel;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class CommentControllerTest extends AdminTestCase
{
    private PostCommentModel $commentModel;

    protected function setUp(): void
    {
        parent::setUp();

        $boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
        $postId  = (int) (new PostModel())->insert([
            'board_id'    => $boardId,
            'title'       => '테스트 게시글',
            'content'     => '본문',
            'author_name' => '글쓴이',
        ]);
        $this->commentModel = new PostCommentModel();
        $this->commentModel->insert([
            'post_id'     => $postId,
            'content'     => '테스트 댓글',
            'author_name' => '댓글 작성자',
        ]);
    }

    public function testAdminCanViewCommentManagementPage(): void
    {
        $result = $this->withSession($this->adminSession)->get('admin/comments');

        $result->assertStatus(200);
        $result->assertSee('테스트 게시글');
        $result->assertSee('테스트 댓글');
    }

    public function testAdminCanToggleCommentVisibility(): void
    {
        $comment = $this->commentModel->first();
        $result  = $this->withSession($this->adminSession)->post("admin/comments/{$comment['id']}/toggle");

        $result->assertRedirect();
        $this->assertSame(0, (int) $this->commentModel->find($comment['id'])['is_active']);
    }

    public function testMemberCannotAccessCommentManagement(): void
    {
        $this->withSession($this->memberSession)->get('admin/comments')->assertStatus(302);
    }
}
