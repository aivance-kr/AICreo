<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BoardModel;
use App\Models\PostModel;
use App\Models\PostRevisionModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class PostRevisionTest extends AdminTestCase
{
    private int $postId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->postId = (int) (new PostModel())->insert([
            'board_id' => (int) (new BoardModel())->getBySlug('notice')['id'],
            'title'    => '원본 제목', 'content' => '<p>원본</p>', 'author_name' => '관리자', 'user_id' => 1,
        ]);
    }

    private function save(string $title, string $content): void
    {
        $this->withSession($this->adminSession)->post("board/notice/{$this->postId}/edit", [
            'title' => $title, 'content' => $content,
        ]);
    }

    public function testEditingPostRecordsPreviousVersion(): void
    {
        $this->save('고친 제목', '<p>고침</p>');

        $revisions = (new PostRevisionModel())->listByPost($this->postId);
        $this->assertCount(1, $revisions);
        $this->assertSame('원본 제목', $revisions[0]['title']);
        $this->assertSame('고친 제목', (new PostModel())->find($this->postId)['title']);
    }

    public function testSavingWithoutChangesRecordsNothing(): void
    {
        $this->save('원본 제목', '<p>원본</p>');

        $this->assertSame([], (new PostRevisionModel())->listByPost($this->postId));
    }

    public function testListAndCompareShowRevision(): void
    {
        $this->save('고친 제목', '<p>고침</p>');
        $revisionId = (int) (new PostRevisionModel())->listByPost($this->postId)[0]['id'];

        $list = $this->withSession($this->adminSession)->get("admin/posts/{$this->postId}/revisions");
        $list->assertOK();
        $list->assertSee('원본 제목');

        $compare = $this->withSession($this->adminSession)->get("admin/posts/{$this->postId}/revisions/{$revisionId}");
        $compare->assertOK();
        $compare->assertSee('- 원본 제목');
        $compare->assertSee('+ 고친 제목');
    }

    public function testRestoreRevertsPostAndKeepsCurrentAsNewRevision(): void
    {
        $this->save('고친 제목', '<p>고침</p>');
        $revisionId = (int) (new PostRevisionModel())->listByPost($this->postId)[0]['id'];

        $this->withSession($this->adminSession)->post("admin/posts/{$this->postId}/revisions/{$revisionId}/restore");

        $this->assertSame('원본 제목', (new PostModel())->find($this->postId)['title']);
        $this->assertCount(2, (new PostRevisionModel())->listByPost($this->postId));
    }

    public function testNonAdminCannotAccessRevisions(): void
    {
        $result = $this->withSession($this->memberSession)->get("admin/posts/{$this->postId}/revisions");

        $this->assertTrue($result->isRedirect() || $result->response()->getStatusCode() === 403);
    }

    public function testUnknownRevisionIsNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->withSession($this->adminSession)->get("admin/posts/{$this->postId}/revisions/999999");
    }
}
