<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

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
}
