<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminLogModel;
use App\Models\BoardModel;
use App\Models\PostModel;
use App\Models\PostPreviewTokenModel;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class PostPreviewLinkTest extends AdminTestCase
{
    private int $postId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->postId = (int) (new PostModel())->insert([
            'board_id' => (int) (new BoardModel())->getBySlug('notice')['id'],
            'title'    => '비공개 글', 'content' => '<p>본문</p>', 'author_name' => '관리자', 'is_active' => 0,
        ]);
    }

    public function testIndexRequiresAdmin(): void
    {
        $this->withSession($this->memberSession)->get("admin/posts/{$this->postId}/preview-links")->assertRedirect();
    }

    public function testIssueCreatesTokenAndShowsLink(): void
    {
        $this->withSession($this->adminSession)->post("admin/posts/{$this->postId}/preview-links", ['days' => '3']);

        $rows = (new PostPreviewTokenModel())->listByPost($this->postId);
        $this->assertCount(1, $rows);

        $body = $this->withSession($this->adminSession)->get("admin/posts/{$this->postId}/preview-links")->getBody();
        $this->assertStringContainsString('/preview/' . $rows[0]['token'], $body);
    }

    public function testIssueRejectsInvalidDays(): void
    {
        $this->withSession($this->adminSession)->post("admin/posts/{$this->postId}/preview-links", ['days' => '0']);

        $this->assertSame([], (new PostPreviewTokenModel())->listByPost($this->postId));
    }

    public function testRevokeInvalidatesToken(): void
    {
        $tokens = new PostPreviewTokenModel();
        $token  = $tokens->issue($this->postId, 7);
        $id     = (int) $tokens->listByPost($this->postId)[0]['id'];

        $this->withSession($this->adminSession)->post("admin/posts/{$this->postId}/preview-links/{$id}/revoke");

        $this->assertNull($tokens->findValid($token));
    }

    public function testAdminLogOmitsTokenValue(): void
    {
        $this->withSession($this->adminSession)->post("admin/posts/{$this->postId}/preview-links", ['days' => '3']);

        $log   = model(AdminLogModel::class)->orderBy('id', 'DESC')->first();
        $token = (new PostPreviewTokenModel())->listByPost($this->postId)[0]['token'];
        $this->assertSame('post', $log['target_type']);
        $this->assertStringNotContainsString($token, (string) $log['summary']);
    }
}
