<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\BoardModel;
use App\Models\PostModel;
use App\Models\PostRevisionModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class PostRevisionModelTest extends DatabaseTestCase
{
    private PostRevisionModel $model;
    private PostModel $posts;
    private int $postId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model  = new PostRevisionModel();
        $this->posts  = new PostModel();
        $boardId      = (int) (new BoardModel())->getBySlug('notice')['id'];
        $this->postId = (int) $this->posts->insert([
            'board_id' => $boardId, 'title' => '원본', 'content' => '<p>원본 본문</p>', 'meta_desc' => '원본 요약', 'author_name' => '관리자',
        ]);
    }

    public function testRecordStoresSnapshotOfPost(): void
    {
        $this->assertTrue($this->model->record($this->posts->find($this->postId), 1));

        $rows = $this->model->listByPost($this->postId);
        $this->assertCount(1, $rows);
        $this->assertSame('원본', $rows[0]['title']);

        $full = $this->model->findForPost($this->postId, (int) $rows[0]['id']);
        $this->assertSame('<p>원본 본문</p>', $full['content']);
        $this->assertSame('원본 요약', $full['meta_desc']);
    }

    public function testListIsNewestFirstAndScopedToPost(): void
    {
        $post = $this->posts->find($this->postId);
        $this->model->record(['title' => '첫째'] + $post, 1);
        $this->model->record(['title' => '둘째'] + $post, 1);
        $this->model->record(['id' => 999999, 'title' => '다른 글'] + $post, 1);

        $titles = array_column($this->model->listByPost($this->postId), 'title');

        $this->assertSame(['둘째', '첫째'], $titles);
    }

    public function testFindForPostRejectsOtherPostsRevision(): void
    {
        $this->model->record($this->posts->find($this->postId), 1);
        $id = (int) $this->model->listByPost($this->postId)[0]['id'];

        $this->assertNull($this->model->findForPost($this->postId + 1, $id));
    }

    public function testKeepsOnlyNewestRevisionsPerPost(): void
    {
        $post = $this->posts->find($this->postId);

        for ($i = 1; $i <= PostRevisionModel::MAX_PER_POST + 3; $i++) {
            $this->model->record(['title' => "v{$i}"] + $post, 1);
        }

        $rows = $this->model->listByPost($this->postId);

        $this->assertCount(PostRevisionModel::MAX_PER_POST, $rows);
        $this->assertSame('v' . (PostRevisionModel::MAX_PER_POST + 3), $rows[0]['title']);
        $this->assertSame('v4', $rows[PostRevisionModel::MAX_PER_POST - 1]['title']);
    }

    public function testRestoreAppliesRevisionAndKeepsCurrentAsNewRevision(): void
    {
        $this->model->record($this->posts->find($this->postId), 1);
        $this->posts->update($this->postId, ['title' => '수정본', 'content' => '<p>수정 본문</p>', 'meta_desc' => '수정 요약']);
        $oldId = (int) $this->model->listByPost($this->postId)[0]['id'];

        $this->assertTrue($this->model->restore($this->postId, $oldId, 1));

        $post = $this->posts->find($this->postId);
        $this->assertSame('원본', $post['title']);
        $this->assertSame('<p>원본 본문</p>', $post['content']);
        $this->assertSame('원본 요약', $post['meta_desc']);

        $rows = $this->model->listByPost($this->postId);
        $this->assertCount(2, $rows);
        $this->assertSame('수정본', $rows[0]['title']);
    }

    public function testRestoreUnknownRevisionReturnsFalse(): void
    {
        $this->assertFalse($this->model->restore($this->postId, 999999, 1));
    }

    public function testChangedDetectsTitleContentOrMetaDescDifference(): void
    {
        $post = $this->posts->find($this->postId);

        $this->assertFalse(PostRevisionModel::changed($post, ['title' => '원본', 'content' => '<p>원본 본문</p>', 'meta_desc' => '원본 요약']));
        $this->assertFalse(PostRevisionModel::changed($post, ['tags' => 'x']));
        $this->assertTrue(PostRevisionModel::changed($post, ['title' => '다름']));
        $this->assertTrue(PostRevisionModel::changed($post, ['content' => '다름']));
        $this->assertTrue(PostRevisionModel::changed($post, ['meta_desc' => '다름']));
    }
}
