<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\BoardModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class BoardModelTest extends DatabaseTestCase
{
    private BoardModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new BoardModel();
    }

    public function testGetBySlugReturnsSeededBoard(): void
    {
        $board = $this->model->getBySlug('notice');

        $this->assertIsArray($board);
        $this->assertSame('공지사항', $board['name']);
    }

    public function testGetBySlugReturnsNullForUnknownSlug(): void
    {
        $this->assertNull($this->model->getBySlug('does-not-exist'));
    }

    public function testGetBySlugIgnoresInactiveBoard(): void
    {
        $this->model->insert([
            'slug'      => 'hidden',
            'name'      => '숨김게시판',
            'is_active' => 0,
        ]);

        $this->assertNull($this->model->getBySlug('hidden'));
    }

    public function testGetActiveBoardsContainsSeededBoards(): void
    {
        $slugs = array_column($this->model->getActiveBoards(), 'slug');

        $this->assertContains('notice', $slugs);
        $this->assertContains('free', $slugs);
        $this->assertContains('qna', $slugs);
    }

    public function testListSkinDefaultsToList(): void
    {
        $this->assertSame('list', $this->model->getBySlug('notice')['list_skin']);
    }

    public function testListSkinIsAllowedFieldAndPersists(): void
    {
        $id = $this->model->insert([
            'slug'      => 'blog-skin-board',
            'name'      => '블로그형게시판',
            'list_skin' => 'blog',
        ]);

        $this->assertSame('blog', $this->model->find($id)['list_skin']);
    }
}
