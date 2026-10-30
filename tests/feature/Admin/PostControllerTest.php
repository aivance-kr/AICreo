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

    public function testBulkCategoryChangesCategoryOfSelectedPosts(): void
    {
        $boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
        $oldId   = (int) (new BoardCategoryModel())->insert(['board_id' => $boardId, 'slug' => 'old', 'name' => '끄적임'], true);
        $newId   = (int) (new BoardCategoryModel())->insert(['board_id' => $boardId, 'slug' => 'new', 'name' => 'AI개발'], true);
        $a       = $this->makePost(['category_id' => $oldId]);
        $b       = $this->makePost(['category_id' => $oldId]);
        $c       = $this->makePost(['category_id' => $oldId]);

        $result = $this->withSession($this->adminSession)->post('admin/posts/bulk-category', [
            'post_ids'           => [$a, $b],
            'target_category_id' => $newId,
        ]);

        $result->assertRedirect();
        $result->assertSessionHas('success');
        $this->assertSame($newId, (int) (new PostModel())->find($a)['category_id']);
        $this->assertSame($newId, (int) (new PostModel())->find($b)['category_id']);
        $this->assertSame($oldId, (int) (new PostModel())->find($c)['category_id']);
    }

    public function testBulkCategorySkipsPostsOfOtherBoards(): void
    {
        $noticeId = (int) (new BoardModel())->getBySlug('notice')['id'];
        $otherId  = (int) (new BoardModel())->insert(['slug' => 'other', 'name' => '다른판'], true);
        $newId    = (int) (new BoardCategoryModel())->insert(['board_id' => $noticeId, 'slug' => 'new', 'name' => 'AI개발'], true);
        $same     = $this->makePost();
        $foreign  = $this->makePost(['board_id' => $otherId]);

        $result = $this->withSession($this->adminSession)->post('admin/posts/bulk-category', [
            'post_ids'           => [$same, $foreign],
            'target_category_id' => $newId,
        ]);

        $result->assertSessionHas('success');
        $this->assertSame($newId, (int) (new PostModel())->find($same)['category_id']);
        $this->assertNull((new PostModel())->find($foreign)['category_id']);
    }

    public function testBulkCategoryRejectsMissingCategory(): void
    {
        $id = $this->makePost();

        $result = $this->withSession($this->adminSession)->post('admin/posts/bulk-category', [
            'post_ids'           => [$id],
            'target_category_id' => 999999,
        ]);

        $result->assertSessionHas('error');
        $this->assertNull((new PostModel())->find($id)['category_id']);
    }

    public function testBulkCategoryRejectsEmptySelection(): void
    {
        $boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
        $newId   = (int) (new BoardCategoryModel())->insert(['board_id' => $boardId, 'slug' => 'new', 'name' => 'AI개발'], true);

        $result = $this->withSession($this->adminSession)->post('admin/posts/bulk-category', [
            'target_category_id' => $newId,
        ]);

        $result->assertSessionHas('error');
    }

    public function testIndexFiltersByCategory(): void
    {
        $boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
        $catId   = (int) (new BoardCategoryModel())->insert(['board_id' => $boardId, 'slug' => 'f', 'name' => '필터카테'], true);
        $this->makePost(['title' => '카테있는글', 'category_id' => $catId]);
        $this->makePost(['title' => '카테없는글']);

        $result = $this->withSession($this->adminSession)->get('admin/posts?category_id=' . $catId);

        $result->assertSee('카테있는글');
        $result->assertDontSee('카테없는글');
    }
}
