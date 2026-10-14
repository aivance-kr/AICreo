<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BoardCategoryModel;
use App\Models\BoardModel;
use App\Models\PostModel;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class PostControllerTest extends AdminTestCase
{
    private function makePost(array $overrides = []): int
    {
        $boardId = (int) (new BoardModel())->getBySlug('notice')['id'];

        return (int) (new PostModel())->insert(array_merge([
            'board_id'    => $boardId,
            'title'       => '제목',
            'content'     => '본문',
            'author_name' => '관리자',
        ], $overrides));
    }

    public function testAdminTogglesPostVisibility(): void
    {
        $id = $this->makePost();

        $result = $this->withSession($this->adminSession)->post("admin/posts/{$id}/toggle");

        $result->assertRedirect();
        $this->assertSame(0, (int) (new PostModel())->find($id)['is_active']);
    }

    public function testToggleTwiceRestoresVisibility(): void
    {
        $id = $this->makePost();

        $this->withSession($this->adminSession)->post("admin/posts/{$id}/toggle");
        $this->withSession($this->adminSession)->post("admin/posts/{$id}/toggle");

        $this->assertSame(1, (int) (new PostModel())->find($id)['is_active']);
    }

    public function testToggleMissingPostRedirectsWithError(): void
    {
        $result = $this->withSession($this->adminSession)->post('admin/posts/999999/toggle');

        $result->assertRedirect();
        $result->assertSessionHas('error');
    }

    public function testBulkMoveMovesPostsAndClearsCategory(): void
    {
        $noticeId = (int) (new BoardModel())->getBySlug('notice')['id'];
        $targetId = (int) (new BoardModel())->insert(['slug' => 'moved', 'name' => '이동대상'], true);
        $catId    = (int) (new BoardCategoryModel())->insert(['board_id' => $noticeId, 'slug' => 'c', 'name' => 'c'], true);
        $a        = $this->makePost(['category_id' => $catId]);
        $b        = $this->makePost();

        $result = $this->withSession($this->adminSession)->post('admin/posts/bulk-move', [
            'post_ids'        => [$a, $b],
            'target_board_id' => $targetId,
        ]);

        $result->assertRedirect();
        $result->assertSessionHas('success');

        foreach ([$a, $b] as $id) {
            $row = (new PostModel())->find($id);
            $this->assertSame($targetId, (int) $row['board_id']);
            $this->assertNull($row['category_id']);
        }
    }

    public function testBulkMoveRejectsMissingTargetBoard(): void
    {
        $id = $this->makePost();

        $result = $this->withSession($this->adminSession)->post('admin/posts/bulk-move', [
            'post_ids'        => [$id],
            'target_board_id' => 999999,
        ]);

        $result->assertSessionHas('error');
        $this->assertSame(
            (int) (new BoardModel())->getBySlug('notice')['id'],
            (int) (new PostModel())->find($id)['board_id'],
        );
    }

    public function testBulkMoveRejectsEmptySelection(): void
    {
        $targetId = (int) (new BoardModel())->insert(['slug' => 'moved', 'name' => '이동대상'], true);

        $result = $this->withSession($this->adminSession)->post('admin/posts/bulk-move', [
            'target_board_id' => $targetId,
        ]);

        $result->assertSessionHas('error');
    }
}
