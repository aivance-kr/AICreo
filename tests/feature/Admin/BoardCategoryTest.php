<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BoardCategoryModel;
use App\Models\BoardModel;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class BoardCategoryTest extends AdminTestCase
{
    private function boardId(string $slug): int
    {
        return (int) (new BoardModel())->getBySlug($slug)['id'];
    }

    public function testAdminCreatesCategory(): void
    {
        $boardId = $this->boardId('free');

        $result = $this->withSession($this->adminSession)->post("admin/boards/{$boardId}/categories", [
            'slug'       => 'daily',
            'name'       => '일상',
            'sort_order' => 1,
        ]);

        $result->assertRedirectTo("/admin/boards/{$boardId}/categories");
        $this->assertSame(1, (new BoardCategoryModel())
            ->where('board_id', $boardId)
            ->where('slug', 'daily')
            ->countAllResults());
    }

    public function testCategoriesAreScopedPerBoard(): void
    {
        $freeId = $this->boardId('free');
        $qnaId  = $this->boardId('qna');

        $freeCategoryId = (int) (new BoardCategoryModel())->insert(['board_id' => $freeId, 'slug' => 'daily', 'name' => '일상'], true);
        $qnaCategoryId  = (int) (new BoardCategoryModel())->insert(['board_id' => $qnaId, 'slug' => 'daily', 'name' => '문의-일상'], true);

        $body = $this->withSession($this->adminSession)->get("admin/boards/{$freeId}/categories")->getBody();

        $this->assertStringContainsString("/categories/{$freeCategoryId}/delete", $body);
        $this->assertStringNotContainsString("/categories/{$qnaCategoryId}/delete", $body);
    }

    public function testAdminUpdatesCategory(): void
    {
        $boardId    = $this->boardId('free');
        $categoryId = (int) (new BoardCategoryModel())->insert(['board_id' => $boardId, 'slug' => 'daily', 'name' => '일상'], true);

        $result = $this->withSession($this->adminSession)->post("admin/boards/{$boardId}/categories/{$categoryId}/edit", [
            'slug'       => 'daily',
            'name'       => '일상수정',
            'sort_order' => 0,
            'is_active'  => 1,
        ]);

        $result->assertRedirectTo("/admin/boards/{$boardId}/categories");
        $this->assertSame('일상수정', (new BoardCategoryModel())->find($categoryId)['name']);
    }

    public function testAdminDeletesCategory(): void
    {
        $boardId    = $this->boardId('free');
        $categoryId = (int) (new BoardCategoryModel())->insert(['board_id' => $boardId, 'slug' => 'temp', 'name' => '임시'], true);

        $result = $this->withSession($this->adminSession)->post("admin/boards/{$boardId}/categories/{$categoryId}/delete");

        $result->assertRedirectTo("/admin/boards/{$boardId}/categories");
        $this->assertNull((new BoardCategoryModel())->find($categoryId));
    }

    public function testGuestCannotManageCategories(): void
    {
        $boardId = $this->boardId('free');

        $result = $this->get("admin/boards/{$boardId}/categories");

        $result->assertRedirect();
    }
}
