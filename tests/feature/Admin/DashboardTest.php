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
final class DashboardTest extends AdminTestCase
{
    public function testDashboardShowsRecentCommentWithPostLink(): void
    {
        $board = (new BoardModel())->getBySlug('qna');
        $this->assertIsArray($board);

        $postId = (int) (new PostModel())->insert([
            'board_id'    => $board['id'],
            'title'       => '댓글 대상 글',
            'content'     => '본문',
            'author_name' => '작성자',
        ]);
        (new PostCommentModel())->insert([
            'post_id'     => $postId,
            'content'     => '대시보드에 표시할 최신 댓글',
            'author_name' => '댓글 작성자',
        ]);

        $result = $this->withSession($this->adminSession)->get('admin/dashboard');

        $result->assertStatus(200);
        $result->assertSee('최근 댓글');
        $result->assertSee('댓글 대상 글');
        $result->assertSee('대시보드에 표시할 최신 댓글');
        $result->assertSee("/board/qna/{$postId}#comments");
    }
}
