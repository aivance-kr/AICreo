<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Libraries\TrashManager;
use App\Models\BoardModel;
use App\Models\PageModel;
use App\Models\PostCommentModel;
use App\Models\PostModel;
use App\Models\SeriesModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class TrashManagerTest extends DatabaseTestCase
{
    private TrashManager $trash;
    private PostModel $posts;
    private int $boardId;

    protected function setUp(): void
    {
        parent::setUp();
        cache()->clean();
        $this->trash   = new TrashManager();
        $this->posts   = new PostModel();
        $this->boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
    }

    private function makePost(string $title = '제목'): int
    {
        return (int) $this->posts->insert([
            'board_id' => $this->boardId, 'title' => $title, 'content' => '본문', 'author_name' => '작성자',
        ]);
    }

    private function makeComment(int $postId, string $content = '댓글'): int
    {
        return (int) (new PostCommentModel())->insert(['post_id' => $postId, 'content' => $content, 'author_name' => '작성자']);
    }

    private function makePage(string $slug = 'trash-test'): int
    {
        return (int) (new PageModel())->insert(['slug' => $slug, 'title' => '소개', 'status' => 'published']);
    }

    private function attach(int $postId): void
    {
        $this->db->table('post_files')->insert([
            'post_id'   => $postId, 'original_name' => 'a.txt', 'stored_name' => 'zz-missing.txt',
            'file_path' => 'uploads/zz-missing.txt', 'file_size' => 1, 'mime_type' => 'text/plain', 'is_image' => 0,
        ]);
    }

    private function link(int $seriesId, int $postId): void
    {
        $this->db->table('post_series')->insert(['series_id' => $seriesId, 'post_id' => $postId, 'series_order' => 1]);
    }

    public function testRestorePostKeepsAttachmentsAndSeriesMembership(): void
    {
        $seriesId = (int) (new SeriesModel())->insert(['slug' => 'demo', 'title' => '연재', 'status' => 'ongoing']);
        $postId   = $this->makePost();
        $this->attach($postId);
        $this->link($seriesId, $postId);

        $this->posts->delete($postId);
        $this->assertNull($this->posts->find($postId));

        $this->assertTrue($this->trash->restore(TrashManager::TYPE_POSTS, $postId));

        $this->assertNotNull($this->posts->find($postId));
        $this->assertSame(1, $this->db->table('post_files')->where('post_id', $postId)->countAllResults());
        $this->assertSame(1, $this->db->table('post_series')->where('post_id', $postId)->countAllResults());
    }

    public function testRestoreIgnoresItemsNotInTrash(): void
    {
        $postId = $this->makePost();

        $this->assertFalse($this->trash->restore(TrashManager::TYPE_POSTS, $postId));
        $this->assertFalse($this->trash->purge(TrashManager::TYPE_POSTS, $postId));
        $this->assertNotNull($this->posts->find($postId));
    }

    public function testPurgePostRemovesDependents(): void
    {
        $seriesId = (int) (new SeriesModel())->insert(['slug' => 'demo', 'title' => '연재', 'status' => 'ongoing']);
        $postId   = $this->makePost();
        $this->attach($postId);
        $this->link($seriesId, $postId);
        $this->makeComment($postId);
        $this->posts->delete($postId);

        $this->assertTrue($this->trash->purge(TrashManager::TYPE_POSTS, $postId));

        $this->assertNull($this->posts->withDeleted()->find($postId));

        foreach (['post_files', 'post_series', 'post_comments'] as $table) {
            $this->assertSame(0, $this->db->table($table)->where('post_id', $postId)->countAllResults(), $table);
        }
    }

    public function testCommentAndPageRestoreAndPurge(): void
    {
        $postId    = $this->makePost();
        $commentId = $this->makeComment($postId);
        $pageId    = $this->makePage();
        $comments  = new PostCommentModel();
        $pages     = new PageModel();

        $comments->delete($commentId);
        $pages->delete($pageId);
        $this->assertNull($pages->find($pageId));

        $this->assertTrue($this->trash->restore(TrashManager::TYPE_COMMENTS, $commentId));
        $this->assertTrue($this->trash->restore(TrashManager::TYPE_PAGES, $pageId));
        $this->assertNotNull($comments->find($commentId));
        $this->assertNotNull($pages->find($pageId));

        $pages->delete($pageId);
        $this->assertTrue($this->trash->purge(TrashManager::TYPE_PAGES, $pageId));
        $this->assertNull($pages->withDeleted()->find($pageId));
    }

    public function testListNormalizesRowsAndCountsPerType(): void
    {
        $postId = $this->makePost('지운 글');
        $this->makeComment($postId, '<b>지운</b> 댓글');
        (new PostCommentModel())->where('post_id', $postId)->delete();
        $this->posts->delete($postId);
        $pages = new PageModel();
        $pages->delete($this->makePage());

        $this->assertSame(['posts' => 1, 'comments' => 1, 'pages' => 1], $this->trash->counts());

        $posts = $this->trash->list(TrashManager::TYPE_POSTS, 1, 10);
        $this->assertSame('지운 글', $posts['items'][0]['title']);
        $this->assertSame(1, $posts['total']);

        $comments = $this->trash->list(TrashManager::TYPE_COMMENTS, 1, 10);
        $this->assertSame('지운 댓글', $comments['items'][0]['title']);
        $this->assertSame('글: 지운 글', $comments['items'][0]['meta']);

        $this->assertSame('/trash-test', $this->trash->list(TrashManager::TYPE_PAGES, 1, 10)['items'][0]['meta']);
    }

    public function testPurgeExpiredRemovesOnlyOldItems(): void
    {
        $old   = $this->makePost('오래된 글');
        $fresh = $this->makePost('최근 글');
        $this->posts->delete($old);
        $this->posts->delete($fresh);
        $this->db->table('posts')->where('id', $old)->update(['deleted_at' => date('Y-m-d H:i:s', strtotime('-40 days'))]);

        $purged = $this->trash->purgeExpired(30);

        $this->assertSame(1, $purged[TrashManager::TYPE_POSTS]);
        $this->assertNull($this->posts->withDeleted()->find($old));
        $this->assertNotNull($this->posts->withDeleted()->find($fresh));
    }

    public function testDeletedCommentsAreNotCountedInPostList(): void
    {
        $postId    = $this->makePost();
        $commentId = $this->makeComment($postId);
        $this->makeComment($postId);
        (new PostCommentModel())->delete($commentId);

        $row = $this->posts->getList($this->boardId, 1, 10)['posts'][0];

        $this->assertSame(1, (int) $row['comment_count']);
    }
}
