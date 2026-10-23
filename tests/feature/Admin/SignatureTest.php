<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BoardModel;
use App\Models\PostModel;
use App\Models\SignatureModel;
use App\Models\UserModel;
use Tests\Support\AdminTestCase;

/**
 * 운영자 글 서명 (#454).
 *
 * @internal
 */
final class SignatureTest extends AdminTestCase
{
    private SignatureModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new SignatureModel();
    }

    private function makeSignature(string $name, bool $selected = false): int
    {
        return (int) $this->model->insert([
            'user_id'     => 1,
            'name'        => $name,
            'content'     => '<p>' . $name . '</p>',
            'is_selected' => (int) $selected,
        ], true);
    }

    private function writePost(string $slug, array $extra = []): ?array
    {
        $this->withSession($this->adminSession)->post("board/{$slug}/write", [
            'title'   => '서명 테스트 글',
            'content' => '<p>본문</p>',
        ] + $extra);

        return (new PostModel())
            ->where('board_id', (new BoardModel())->getBySlug($slug)['id'])
            ->where('title', '서명 테스트 글')
            ->first();
    }

    public function testStoreSanitizesContentAndSelectsFirstSignature(): void
    {
        $this->withSession($this->adminSession)->post('admin/settings/signatures', [
            'name'    => '기본',
            'content' => '<p>감사합니다</p><script>alert(1)</script>',
        ])->assertRedirectTo('/admin/settings/signatures');

        $rows = $this->model->listByUser(1);
        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]['is_selected']);
        $this->assertStringNotContainsString('script', $rows[0]['content']);
    }

    public function testStoreRejectsEmptyContent(): void
    {
        $this->withSession($this->adminSession)->post('admin/settings/signatures', [
            'name'    => '빈 서명',
            'content' => '<p> </p>',
        ]);

        $this->assertSame([], $this->model->listByUser(1));
    }

    public function testSelectKeepsOnlyOneSelected(): void
    {
        $first  = $this->makeSignature('첫째', true);
        $second = $this->makeSignature('둘째');

        $this->withSession($this->adminSession)->post("admin/settings/signatures/{$second}/select");

        $this->assertSame($second, (int) $this->model->selectedByUser(1)['id']);
        $this->assertSame(0, (int) $this->model->find($first)['is_selected']);
    }

    public function testCannotTouchOtherUsersSignature(): void
    {
        $otherUserId = (int) (new UserModel())->insert([
            'username' => 'other-admin',
            'email'    => 'other@example.com',
            'password' => password_hash('secret123', PASSWORD_DEFAULT),
            'nickname' => '다른 운영자',
            'role'     => 'admin',
        ]);
        $otherId = (int) $this->model->insert([
            'user_id' => $otherUserId, 'name' => '남의 것', 'content' => '<p>x</p>', 'is_selected' => 0,
        ], true);

        $this->withSession($this->adminSession)->post("admin/settings/signatures/{$otherId}/delete");

        $this->assertNotNull($this->model->find($otherId));
    }

    public function testPostGetsSignatureSnapshotWhenChecked(): void
    {
        $this->makeSignature('스냅샷', true);

        $post = $this->writePost('notice', ['use_signature' => '1']);

        $this->assertSame('<p>스냅샷</p>', $post['signature']);
    }

    public function testPostWithoutCheckboxHasNoSignature(): void
    {
        $this->makeSignature('스냅샷', true);

        $this->assertNull($this->writePost('notice')['signature']);
    }

    public function testChangingSignatureDoesNotAlterExistingPost(): void
    {
        $id   = $this->makeSignature('이전', true);
        $post = $this->writePost('notice', ['use_signature' => '1']);

        $this->model->update($id, ['content' => '<p>변경</p>']);

        $this->assertSame('<p>이전</p>', (new PostModel())->find($post['id'])['signature']);
    }

    public function testViewRendersSignatureBelowContent(): void
    {
        $this->makeSignature('보이는서명', true);
        $post = $this->writePost('notice', ['use_signature' => '1']);

        $body = $this->get("board/notice/{$post['id']}")->getBody();

        $this->assertStringContainsString('post-signature', $body);
        $this->assertStringContainsString('보이는서명', html_entity_decode($body) ?: $body);
    }

    public function testWriteFormShowsCheckboxOnlyWithSelectedSignature(): void
    {
        $this->assertStringNotContainsString(
            'name="use_signature"',
            $this->withSession($this->adminSession)->get('board/notice/write')->getBody(),
        );

        $this->makeSignature('폼', true);

        $this->assertStringContainsString(
            'name="use_signature"',
            $this->withSession($this->adminSession)->get('board/notice/write')->getBody(),
        );
    }

    public function testManagePageRenders(): void
    {
        $this->withSession($this->adminSession)->get('admin/settings/signatures')->assertStatus(200);
    }

    public function testApplyBoardCommandFillsOnlyEmptySignatures(): void
    {
        $this->makeSignature('일괄', true);
        $boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
        $posts   = new PostModel();
        $empty   = (int) $posts->insert(['board_id' => $boardId, 'title' => 'a', 'content' => 'a', 'author_name' => 'x'], true);
        $keep    = (int) $posts->insert(['board_id' => $boardId, 'title' => 'b', 'content' => 'b', 'author_name' => 'x', 'signature' => '<p>기존</p>'], true);

        command('signature:apply-board notice');

        $this->assertSame('<p>일괄</p>', $posts->find($empty)['signature']);
        $this->assertSame('<p>기존</p>', $posts->find($keep)['signature']);
    }
}
