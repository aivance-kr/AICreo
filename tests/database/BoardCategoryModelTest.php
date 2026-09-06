<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\BoardCategoryModel;
use App\Models\BoardModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class BoardCategoryModelTest extends DatabaseTestCase
{
    private BoardCategoryModel $model;
    private int $boardId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model   = new BoardCategoryModel();
        $this->boardId = (int) (new BoardModel())->getBySlug('free')['id'];
    }

    public function testGetByBoardReturnsOnlyActiveOrdered(): void
    {
        $this->model->insert(['board_id' => $this->boardId, 'slug' => 'b', 'name' => 'B', 'sort_order' => 2, 'is_active' => 1]);
        $this->model->insert(['board_id' => $this->boardId, 'slug' => 'a', 'name' => 'A', 'sort_order' => 1, 'is_active' => 1]);
        $this->model->insert(['board_id' => $this->boardId, 'slug' => 'hidden', 'name' => '숨김', 'sort_order' => 0, 'is_active' => 0]);

        $names = array_column($this->model->getByBoard($this->boardId), 'name');

        $this->assertSame(['A', 'B'], $names);
    }

    public function testGetByBoardIsScopedPerBoard(): void
    {
        $otherBoardId = (int) (new BoardModel())->getBySlug('qna')['id'];
        $this->model->insert(['board_id' => $this->boardId, 'slug' => 'notice', 'name' => '공지', 'is_active' => 1]);
        $this->model->insert(['board_id' => $otherBoardId, 'slug' => 'notice', 'name' => '문의공지', 'is_active' => 1]);

        $names = array_column($this->model->getByBoard($this->boardId), 'name');

        $this->assertSame(['공지'], $names);
    }

    public function testGetAllByBoardIncludesInactive(): void
    {
        $this->model->insert(['board_id' => $this->boardId, 'slug' => 'hidden', 'name' => '숨김', 'is_active' => 0]);

        $names = array_column($this->model->getAllByBoard($this->boardId), 'name');

        $this->assertContains('숨김', $names);
    }

    public function testGetBySlugIgnoresInactive(): void
    {
        $this->model->insert(['board_id' => $this->boardId, 'slug' => 'hidden', 'name' => '숨김', 'is_active' => 0]);

        $this->assertNull($this->model->getBySlug($this->boardId, 'hidden'));
    }
}
