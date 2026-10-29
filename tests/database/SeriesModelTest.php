<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\BoardModel;
use App\Models\PostModel;
use App\Models\SeriesModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class SeriesModelTest extends DatabaseTestCase
{
    private SeriesModel $model;
    private PostModel $posts;
    private int $boardId;
    private int $seriesId;

    protected function setUp(): void
    {
        parent::setUp();
        cache()->clean();
        $this->model    = new SeriesModel();
        $this->posts    = new PostModel();
        $this->boardId  = (int) (new BoardModel())->getBySlug('notice')['id'];
        $this->seriesId = (int) $this->model->insert(['slug' => 'demo', 'title' => '데모 연재', 'status' => 'ongoing']);
    }

    private function makePost(string $title, ?int $order, array $overrides = []): int
    {
        $postId = (int) $this->posts->insert(array_merge([
            'board_id'    => $this->boardId,
            'title'       => $title,
            'content'     => '본문',
            'author_name' => '작성자',
        ], $overrides));
        $this->link($this->seriesId, $postId, $order);

        return $postId;
    }

    private function link(int $seriesId, int $postId, ?int $order): void
    {
        $this->db->table('post_series')->insert(['series_id' => $seriesId, 'post_id' => $postId, 'series_order' => $order]);
    }

    public function testNavigationOrdersBySeriesOrderThenId(): void
    {
        $third  = $this->makePost('3편', 3);
        $first  = $this->makePost('1편', 1);
        $second = $this->makePost('2편', 2);

        $nav = $this->model->navigation($this->seriesId, $second);

        $this->assertNotNull($nav);
        $this->assertSame(2, $nav['current']);
        $this->assertSame(3, $nav['total']);
        $this->assertSame($first, (int) $nav['prev']['id']);
        $this->assertSame($third, (int) $nav['next']['id']);
        $this->assertSame('데모 연재', $nav['series']['title']);
    }

    public function testEqualOrderFallsBackToPostId(): void
    {
        $a = $this->makePost('A', 1);
        $b = $this->makePost('B', 1);

        $nav = $this->model->navigation($this->seriesId, $b);

        $this->assertSame($a, (int) $nav['prev']['id']);
        $this->assertNull($nav['next']);
    }

    public function testFirstAndLastHaveNoPrevOrNext(): void
    {
        $first = $this->makePost('1편', 1);
        $last  = $this->makePost('2편', 2);

        $this->assertNull($this->model->navigation($this->seriesId, $first)['prev']);
        $this->assertNull($this->model->navigation($this->seriesId, $last)['next']);
    }

    public function testHiddenSecretAndDeletedPostsAreExcluded(): void
    {
        $visible = $this->makePost('공개', 1);
        $this->makePost('숨김', 2, ['is_active' => 0]);
        $this->makePost('비밀', 3, ['is_secret' => 1]);
        $deleted = $this->makePost('삭제', 4);
        $this->posts->delete($deleted);

        $nav = $this->model->navigation($this->seriesId, $visible);

        $this->assertSame(1, $nav['total']);
    }

    public function testNavigationIsNullWhenCurrentPostIsNotListed(): void
    {
        $this->makePost('공개', 1);
        $secret = $this->makePost('비밀', 2, ['is_secret' => 1]);

        $this->assertNull($this->model->navigation($this->seriesId, $secret));
    }

    public function testCacheIsClearedWhenPostChanges(): void
    {
        $first = $this->makePost('1편', 1);
        $this->assertSame(1, $this->model->navigation($this->seriesId, $first)['total']);

        $second = $this->makePost('2편', 2);
        $this->model->attachPosts($this->seriesId, [$second]);
        $this->assertSame(2, $this->model->navigation($this->seriesId, $first)['total']);

        $this->posts->update($first, ['is_active' => 0]);
        $this->assertNull($this->model->navigation($this->seriesId, $first));
    }

    public function testNextOrderIsMaxPlusOne(): void
    {
        $this->assertSame(1, $this->model->nextOrder($this->seriesId));

        $this->makePost('1편', 1);
        $this->makePost('5편', 5);

        $this->assertSame(6, $this->model->nextOrder($this->seriesId));
    }

    public function testDeleteDetachesPostsButKeepsThem(): void
    {
        $postId = $this->makePost('1편', 1);

        $this->model->delete($this->seriesId);

        $this->assertNotNull($this->posts->find($postId));
        $this->assertSame([], $this->model->seriesOfPost($postId));
        $this->assertNull($this->model->navigation($this->seriesId, $postId));
    }

    public function testParsePostIdsDedupesAndSkipsGarbage(): void
    {
        $this->assertSame([12, 5, 7], SeriesModel::parsePostIds(' 12, 5,,abc, 12 ,0, -3, 7 '));
        $this->assertSame([], SeriesModel::parsePostIds(''));
    }

    public function testAttachPostsAppendsInInputOrderAndKeepsOtherSeries(): void
    {
        $inSeries = $this->makePost('이미', 3);
        $otherId  = (int) $this->model->insert(['slug' => 'other', 'title' => '다른 연재']);
        $shared   = $this->makePost('공유', 1);
        $this->link($otherId, $shared, 1);
        $this->model->detachPost($this->seriesId, $shared);
        $free = $this->makePost('무소속', null);
        $this->model->detachPost($this->seriesId, $free);

        $result = $this->model->attachPosts($this->seriesId, [$free, $inSeries, $shared, 99999]);

        $this->assertSame([$free, $shared], $result['added']);
        $this->assertSame([$inSeries], $result['already']);
        $this->assertSame([99999], $result['missing']);
        $this->assertSame([$this->seriesId => 5, $otherId => 1], $this->model->seriesOfPost($shared));
        $this->assertSame(4, $this->model->seriesOfPost($free)[$this->seriesId]);
    }

    public function testPostCanBelongToManySeriesAndSyncReplacesMembership(): void
    {
        $otherId = (int) $this->model->insert(['slug' => 'other', 'title' => '다른 연재']);
        $postId  = $this->makePost('글', 1);

        $this->model->syncPostSeries($postId, [$this->seriesId => null, $otherId => 5]);
        $this->assertSame([$this->seriesId => 1, $otherId => 5], $this->model->seriesOfPost($postId));
        $this->assertCount(2, $this->model->navigationsForPost($postId));

        $this->model->syncPostSeries($postId, [$otherId => null, 99999 => 1]);
        $this->assertSame([$otherId => 5], $this->model->seriesOfPost($postId));
        $this->assertNull($this->model->navigation($this->seriesId, $postId));
    }

    public function testListPublicCountsOnlyPublicPostsAndHidesEmptySeries(): void
    {
        $this->makePost('공개', 1);
        $this->makePost('비밀', 2, ['is_secret' => 1]);
        $this->model->insert(['slug' => 'empty', 'title' => '빈 연재']);

        $list = $this->model->listPublic();

        $this->assertCount(1, $list);
        $this->assertSame('demo', $list[0]['slug']);
        $this->assertSame(1, (int) $list[0]['post_count']);
    }

    public function testDetachPostOnlyAffectsOwnSeries(): void
    {
        $postId = $this->makePost('1편', 1);

        $this->model->detachPost($this->seriesId + 999, $postId);
        $this->assertArrayHasKey($this->seriesId, $this->model->seriesOfPost($postId));

        $this->model->detachPost($this->seriesId, $postId);
        $this->assertSame([], $this->model->seriesOfPost($postId));
    }

    public function testSearchPostsMatchesTitleOrId(): void
    {
        $postId = $this->makePost('검색대상글', 1);
        $this->makePost('다른글', 2);

        $byTitle = $this->model->searchPosts($this->seriesId, '검색대상');
        $this->assertSame([$postId], array_map('intval', array_column($byTitle, 'id')));
        $this->assertContains($postId, array_map('intval', array_column($this->model->searchPosts($this->seriesId, (string) $postId), 'id')));
        $this->assertSame([], $this->model->searchPosts($this->seriesId, '  '));
    }

    public function testReorderPostsRenumbersInGivenOrder(): void
    {
        $a = $this->makePost('A', 1);
        $b = $this->makePost('B', 2);
        $c = $this->makePost('C', 3);

        $this->model->reorderPosts($this->seriesId, [$c, 99999, $a, $b]);

        $this->assertSame([$c, $a, $b], array_map('intval', array_column($this->model->publicPosts($this->seriesId), 'id')));
        $this->assertSame(2, $this->model->seriesOfPost($a)[$this->seriesId]);
    }
}
