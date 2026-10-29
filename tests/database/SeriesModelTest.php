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
        return (int) $this->posts->insert(array_merge([
            'board_id'     => $this->boardId,
            'title'        => $title,
            'content'      => '본문',
            'author_name'  => '작성자',
            'series_id'    => $this->seriesId,
            'series_order' => $order,
        ], $overrides));
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
        $this->assertSame(2, $this->model->navigation($this->seriesId, $first)['total']);

        // 다른 시리즈로 옮기면 이전 시리즈 캐시도 지워진다
        $otherId = (int) $this->model->insert(['slug' => 'other', 'title' => '다른 연재']);
        $this->posts->update($second, ['series_id' => $otherId]);
        $this->assertSame(1, $this->model->navigation($this->seriesId, $first)['total']);
        $this->assertSame(1, $this->model->navigation($otherId, $second)['total']);

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

        $post = $this->posts->find($postId);
        $this->assertNotNull($post);
        $this->assertNull($post['series_id']);
        $this->assertNull($post['series_order']);
        $this->assertNull($this->model->navigation($this->seriesId, $postId));
    }
}
