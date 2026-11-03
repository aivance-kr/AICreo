<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BoardModel;
use App\Models\PageModel;
use App\Models\PostModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class TrashControllerTest extends AdminTestCase
{
    private function trashedPostId(string $title): int
    {
        $model = new PostModel();
        $id    = (int) $model->insert([
            'board_id' => (int) (new BoardModel())->getBySlug('notice')['id'],
            'title'    => $title, 'content' => '본문', 'author_name' => '작성자',
        ]);
        $model->delete($id);

        return $id;
    }

    public function testListShowsTrashedItemsByType(): void
    {
        $this->trashedPostId('휴지통 글');
        $pages = new PageModel();
        $pages->delete((int) $pages->insert(['slug' => 'gone', 'title' => '지운 페이지', 'status' => 'published']));

        $posts = $this->withSession($this->adminSession)->get('admin/trash');
        $posts->assertSee('휴지통 글');
        $posts->assertDontSee('지운 페이지');

        $this->withSession($this->adminSession)->get('admin/trash?type=pages')->assertSee('지운 페이지');
    }

    public function testRestoreBringsPostBack(): void
    {
        $id = $this->trashedPostId('복원할 글');

        $this->withSession($this->adminSession)->post("admin/trash/posts/{$id}/restore");

        $this->assertNotNull((new PostModel())->find($id));
    }

    public function testPurgeRemovesRowForGood(): void
    {
        $id = $this->trashedPostId('영구 삭제할 글');

        $this->withSession($this->adminSession)->post("admin/trash/posts/{$id}/delete");

        $this->assertNull((new PostModel())->withDeleted()->find($id));
    }

    public function testMemberCannotRestore(): void
    {
        $id = $this->trashedPostId('회원 금지');

        $this->withSession($this->memberSession)->post("admin/trash/posts/{$id}/restore");

        $this->assertNull((new PostModel())->find($id));
    }

    public function testUnknownTypeIsNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->withSession($this->adminSession)->get('admin/trash?type=users');
    }
}
