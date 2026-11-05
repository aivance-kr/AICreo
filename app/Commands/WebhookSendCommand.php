<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\WebhookSender;
use App\Models\SettingModel;
use App\Models\WebhookDeliveryModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * 웹훅 큐(webhook_deliveries)를 비운다. 1분에 한 번 크론으로 실행한다.
 * 실패한 건은 늘어나는 간격으로 다시 시도하고, MAX_ATTEMPTS 번 실패하면 포기한다.
 */
class WebhookSendCommand extends BaseCommand
{
    protected $group       = 'Blog';
    protected $name        = 'webhook:send';
    protected $description = '큐에 쌓인 웹훅 알림을 발송합니다.';
    protected $usage       = 'webhook:send [--limit 50]';
    protected $options     = ['--limit' => '한 번에 보낼 최대 건수 (기본 50)'];

    public function run(array $params)
    {
        $limit  = max(1, (int) (CLI::getOption('limit') ?? 50));
        $queue  = new WebhookDeliveryModel();
        $url    = trim((string) ((new SettingModel())->getAllAsMap()['webhook_url'] ?? ''));
        $sender = new WebhookSender();
        $sent   = 0;
        $failed = 0;

        // URL 을 지웠으면 보내지 않고 큐를 그대로 둔다(다시 설정하면 이어서 보낸다)
        foreach ($url === '' ? [] : $queue->due($limit) as $row) {
            try {
                $payload = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
                $error   = $sender->send($url, is_array($payload) ? $payload : []);
            } catch (Throwable $e) {
                $error = 'payload: ' . $e->getMessage();
            }

            if ($error === null) {
                $queue->markSent((int) $row['id']);
                $sent++;

                continue;
            }

            $queue->markFailed($row, $error);
            $failed++;
            // URL(토큰)은 로그에 남기지 않는다
            log_message('error', sprintf('[Webhook] 발송 실패 id=%d event=%s try=%d: %s', $row['id'], $row['event'], (int) $row['attempts'] + 1, $error));
        }

        $queue->purgeFinished(30);

        CLI::write(sprintf('완료: 발송 %d건, 실패 %d건(재시도 또는 포기).', $sent, $failed), $failed > 0 ? 'yellow' : 'green');
    }
}
