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

    public function testReorderByBoardRewritesOnlyThatBoardsSortOrder(): void
    {
        $idA = (int) $this->model->insert(['board_id' => $this->boardId, 'slug' => 'a', 'name' => 'A', 'sort_order' => 1], true);
        $idB = (int) $this->model->insert(['board_id' => $this->boardId, 'slug' => 'b', 'name' => 'B', 'sort_order' => 2], true);

        $this->assertTrue($this->model->reorderByBoard($this->boardId, [$idB, $idA]));
        $this->assertSame('0', $this->model->find($idB)['sort_order']);
        $this->assertSame('1', $this->model->find($idA)['sort_order']);
    }

    public function testReorderByBoardRejectsForeignOrMissingCategoryIds(): void
    {
        $id           = (int) $this->model->insert(['board_id' => $this->boardId, 'slug' => 'local', 'name' => '로컬'], true);
        $otherBoardId = (int) (new BoardModel())->getBySlug('qna')['id'];
        $foreignId    = (int) $this->model->insert(['board_id' => $otherBoardId, 'slug' => 'foreign', 'name' => '외부'], true);

        $this->assertFalse($this->model->reorderByBoard($this->boardId, [$id, $foreignId]));
        $this->assertSame('0', $this->model->find($id)['sort_order']);
    }
}
