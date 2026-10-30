<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\NewsletterMailer;
use App\Models\SubscriberModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * 발송 큐(newsletter_deliveries)에 쌓인 새 글 알림을 보낸다. 1분에 한 번 크론으로 실행한다.
 * 한 번에 --limit 건(기본 50)만 처리해 다음 실행으로 넘기므로 구독자가 많아도 실행 시간이 늘어나지 않는다.
 */
class NewsletterSendCommand extends BaseCommand
{
    protected $group       = 'Blog';
    protected $name        = 'newsletter:send';
    protected $description = '큐에 쌓인 새 글 뉴스레터를 발송합니다.';
    protected $usage       = 'newsletter:send [--limit 50]';
    protected $options     = ['--limit' => '한 번에 보낼 최대 건수 (기본 50)'];

    public function run(array $params)
    {
        $limit       = max(1, (int) (CLI::getOption('limit') ?? 50));
        $subscribers = new SubscriberModel();
        $mailer      = new NewsletterMailer();
        $sent        = 0;
        $failed      = 0;

        foreach ($subscribers->pendingDeliveries($limit) as $delivery) {
            if ($mailer->sendPost($delivery)) {
                $subscribers->markSent((int) $delivery['delivery_id']);
                $sent++;
            } else {
                $failed++;
            }
        }

        CLI::write(sprintf('완료: 발송 %d건, 실패 %d건(다음 실행에서 재시도).', $sent, $failed), $failed > 0 ? 'yellow' : 'green');
    }
}
