<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\BoardModel;
use App\Models\PostModel;
use App\Models\SubscriberModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class SubscriberModelTest extends DatabaseTestCase
{
    private SubscriberModel $model;
    private PostModel $posts;
    private int $boardId;

    protected function setUp(): void
    {
        parent::setUp();
        cache()->clean();
        $this->model   = new SubscriberModel();
        $this->posts   = new PostModel();
        $this->boardId = (int) (new BoardModel())->getBySlug('notice')['id'];
    }

    private function confirmedSubscriber(string $email): array
    {
        $row = $this->model->subscribe($email);
        $this->model->confirm($row['confirm_token']);

        return $this->model->find($row['id']);
    }

    private function makePost(array $overrides = []): int
    {
        return (int) $this->posts->insert(array_merge([
            'board_id'    => $this->boardId,
            'title'       => '새 글',
            'content'     => '<p>본문</p>',
            'author_name' => '작성자',
        ], $overrides));
    }

    private function deliveryCount(): int
    {
        return $this->db->table('newsletter_deliveries')->countAllResults();
    }

    public function testSubscribeCreatesPendingWithTokens(): void
    {
        $row = $this->model->subscribe('  Reader@Example.com ');

        $this->assertSame('reader@example.com', $row['email']);
        $this->assertSame(SubscriberModel::STATUS_PENDING, $row['status']);
        $this->assertSame(64, strlen($row['confirm_token']));
        $this->assertNotSame($row['confirm_token'], $row['unsubscribe_token']);
    }

    public function testSubscribeAgainWhilePendingIssuesNewConfirmToken(): void
    {
        $first  = $this->model->subscribe('a@example.com');
        $second = $this->model->subscribe('a@example.com');

        $this->assertSame($first['id'], $second['id']);
        $this->assertNotSame($first['confirm_token'], $second['confirm_token']);
        $this->assertSame($first['unsubscribe_token'], $second['unsubscribe_token']);
        $this->assertFalse($this->model->confirm($first['confirm_token']));
    }

    public function testSubscribeConfirmedReturnsNullAndKeepsStatus(): void
    {
        $this->confirmedSubscriber('a@example.com');

        $this->assertNull($this->model->subscribe('a@example.com'));
        $this->assertSame(SubscriberModel::STATUS_CONFIRMED, $this->model->where('email', 'a@example.com')->first()['status']);
    }

    public function testConfirmActivatesAndIsIdempotent(): void
    {
        $row = $this->model->subscribe('a@example.com');

        $this->assertTrue($this->model->confirm($row['confirm_token']));
        $this->assertTrue($this->model->confirm($row['confirm_token']));
        $this->assertFalse($this->model->confirm('nope'));
        $this->assertSame(SubscriberModel::STATUS_CONFIRMED, $this->model->find($row['id'])['status']);
        $this->assertNotNull($this->model->find($row['id'])['confirmed_at']);
    }

    public function testResubscribeAfterUnsubscribeRequiresConfirmationAgain(): void
    {
        $sub = $this->confirmedSubscriber('a@example.com');
        $this->model->unsubscribe($sub['unsubscribe_token']);

        $again = $this->model->subscribe('a@example.com');

        $this->assertSame(SubscriberModel::STATUS_PENDING, $again['status']);
        $this->assertNull($again['unsubscribed_at']);
    }

    public function testConfirmRejectsUnsubscribedStaleLink(): void
    {
        $row = $this->model->subscribe('a@example.com');
        $this->model->confirm($row['confirm_token']);
        $this->model->unsubscribe($row['unsubscribe_token']);

        $this->assertFalse($this->model->confirm($row['confirm_token']));
        $this->assertSame(SubscriberModel::STATUS_UNSUBSCRIBED, $this->model->find($row['id'])['status']);
    }

    public function testPublishingPostQueuesOnlyConfirmedSubscribers(): void
    {
        $this->confirmedSubscriber('ok@example.com');
        $this->model->subscribe('pending@example.com');
        $gone = $this->confirmedSubscriber('gone@example.com');
        $this->model->unsubscribe($gone['unsubscribe_token']);

        $postId = $this->makePost();

        $this->assertSame(1, $this->deliveryCount());
        $pending = $this->model->pendingDeliveries(10);
        $this->assertCount(1, $pending);
        $this->assertSame('ok@example.com', $pending[0]['email']);
        $this->assertSame($postId, (int) $pending[0]['post_id']);
    }

    public function testEditingPublishedPostDoesNotQueueAgain(): void
    {
        $postId = $this->makePost();
        $this->confirmedSubscriber('late@example.com');

        $this->posts->update($postId, ['title' => '수정']);

        $this->assertSame(0, $this->deliveryCount());
    }

    public function testHiddenAndScheduledPostsAreNotQueuedUntilPublic(): void
    {
        $this->confirmedSubscriber('ok@example.com');

        $hidden = $this->makePost(['is_active' => 0]);
        $this->assertSame(0, $this->deliveryCount());

        $this->posts->update($hidden, ['is_active' => 1]);
        $this->assertSame(1, $this->deliveryCount());
    }

    public function testSecretPostIsNotQueued(): void
    {
        $this->confirmedSubscriber('ok@example.com');

        $this->makePost(['is_secret' => 1]);

        $this->assertSame(0, $this->deliveryCount());
    }

    public function testPostOnMemberWritableBoardIsNotQueued(): void
    {
        $this->confirmedSubscriber('ok@example.com');
        $this->db->table('boards')->where('id', $this->boardId)->update(['write_permission' => 'member']);

        $this->makePost();

        $this->assertSame(0, $this->deliveryCount());
    }

    public function testScheduledPostIsQueuedWhenPublishDueRuns(): void
    {
        $this->confirmedSubscriber('ok@example.com');
        $postId = $this->makePost(['is_active' => 0, 'publish_at' => date('Y-m-d H:i:s', time() - 60)]);
        $this->assertSame(0, $this->deliveryCount());

        $this->posts->publishDue();

        $this->assertSame(1, $this->deliveryCount());
        $this->assertSame($postId, (int) $this->model->pendingDeliveries(10)[0]['post_id']);
    }

    public function testUnsubscribeDropsUnsentQueueSoNothingIsSentAfter(): void
    {
        $sub = $this->confirmedSubscriber('a@example.com');
        $this->makePost();
        $this->assertCount(1, $this->model->pendingDeliveries(10));

        $this->assertTrue($this->model->unsubscribe($sub['unsubscribe_token']));
        $this->assertFalse($this->model->unsubscribe('nope'));

        $this->assertSame([], $this->model->pendingDeliveries(10));
        $this->assertSame(0, $this->deliveryCount());
    }

    public function testMarkSentRemovesFromPending(): void
    {
        $this->confirmedSubscriber('a@example.com');
        $this->makePost();
        $delivery = $this->model->pendingDeliveries(10)[0];

        $this->model->markSent((int) $delivery['delivery_id']);

        $this->assertSame([], $this->model->pendingDeliveries(10));
    }

    public function testPendingDeliveriesSkipPostHiddenAfterQueuing(): void
    {
        $this->confirmedSubscriber('a@example.com');
        $postId = $this->makePost();

        $this->posts->update($postId, ['is_active' => 0]);

        $this->assertSame([], $this->model->pendingDeliveries(10));
    }

    public function testRemoveDeletesSubscriberAndQueue(): void
    {
        $sub = $this->confirmedSubscriber('a@example.com');
        $this->makePost();

        $this->model->remove((int) $sub['id']);

        $this->assertNull($this->model->find($sub['id']));
        $this->assertSame(0, $this->deliveryCount());
    }

    public function testSearchFiltersByKeywordAndStatus(): void
    {
        $this->confirmedSubscriber('alpha@example.com');
        $this->model->subscribe('beta@example.com');

        $this->assertSame(1, $this->model->search('alpha', '', 1, 20)['total']);
        $this->assertSame(1, $this->model->search('', SubscriberModel::STATUS_PENDING, 1, 20)['total']);
        $this->assertSame(2, $this->model->search('', '', 1, 20)['total']);
        $this->assertSame(1, $this->model->countByStatus()[SubscriberModel::STATUS_CONFIRMED]);
    }
}
