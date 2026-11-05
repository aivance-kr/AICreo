<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SettingModel;
use App\Models\WebhookDeliveryModel;
use CodeIgniter\I18n\Time;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class WebhookTest extends DatabaseTestCase
{
    private WebhookDeliveryModel $queue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queue = new WebhookDeliveryModel();
        $this->configure(['webhook_url' => 'https://hooks.example/x', 'webhook_on_inquiry' => '1', 'webhook_on_comment' => '0', 'webhook_on_subscriber' => '1']);
    }

    /**
     * @param array<string, string> $values
     */
    private function configure(array $values): void
    {
        (new SettingModel())->saveSettings($values);
        cache()->delete('site_settings');
    }

    public function testEnqueueStoresPayloadForEnabledEvent(): void
    {
        $this->queue->enqueue('inquiry', '<b>홍길동</b> & 문의', ['id' => 7]);

        $row = $this->queue->first();
        $this->assertSame('inquiry', $row['event']);
        $payload = json_decode($row['payload'], true);
        $this->assertStringContainsString('&lt;b&gt;홍길동&lt;/b&gt; &amp; 문의', $payload['text']);
        $this->assertSame(7, $payload['data']['id']);
        $this->assertStringNotContainsString('hooks.example', $row['payload']);
    }

    public function testEnqueueSkipsDisabledEventAndEmptyUrl(): void
    {
        $this->queue->enqueue('comment', 'x', []);
        $this->configure(['webhook_url' => '']);
        $this->queue->enqueue('inquiry', 'x', []);

        $this->assertSame(0, $this->queue->countAllResults());
    }

    public function testFailureBacksOffThenGivesUp(): void
    {
        $this->queue->enqueue('inquiry', 'x', []);
        $row = $this->queue->first();

        $this->queue->markFailed($row, 'http 500');
        $row = $this->queue->first();
        $this->assertSame(1, (int) $row['attempts']);
        $this->assertNull($row['failed_at']);
        $this->assertSame([], $this->queue->due(10), '대기 시간 안에는 다시 보내지 않는다');

        $this->db->table('webhook_deliveries')->update(['next_attempt_at' => Time::now()->subMinutes(1)->toDateTimeString()]);
        $this->assertCount(1, $this->queue->due(10));

        $row['attempts'] = WebhookDeliveryModel::MAX_ATTEMPTS - 1;
        $this->queue->markFailed($row, 'http 500');
        $this->assertNotNull($this->queue->first()['failed_at']);
        $this->assertSame([], $this->queue->due(10));
    }

    public function testSendCommandRecordsFailureWithoutThrowing(): void
    {
        $this->configure(['webhook_url' => 'http://127.0.0.1:1/hook']);
        $this->queue->enqueue('inquiry', 'x', []);

        command('webhook:send');

        $row = $this->queue->first();
        $this->assertNull($row['sent_at']);
        $this->assertSame(1, (int) $row['attempts']);
        $this->assertStringNotContainsString('127.0.0.1:1/hook', (string) $row['last_error']);
    }

    public function testPurgeRemovesOldFinishedRowsOnly(): void
    {
        $this->queue->enqueue('inquiry', 'old', []);
        $this->queue->enqueue('inquiry', 'pending', []);
        $old = $this->queue->first();
        $this->db->table('webhook_deliveries')->where('id', $old['id'])
            ->update(['sent_at' => Time::now()->subDays(40)->toDateTimeString()]);

        $this->queue->purgeFinished(30);

        $this->assertSame(1, $this->queue->countAllResults());
    }
}
