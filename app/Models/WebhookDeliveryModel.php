<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;
use Throwable;

/**
 * 웹훅 발송 큐. 요청 흐름에서는 enqueue() 만 하고, `webhook:send` 가 비동기로 보낸다.
 */
class WebhookDeliveryModel extends Model
{
    /**
     * 이벤트 키 => 화면 표시 이름 (설정 키는 webhook_on_{키})
     */
    public const EVENTS = [
        'inquiry'    => '새 문의',
        'comment'    => '새 댓글',
        'subscriber' => '구독 신청',
    ];

    public const MAX_ATTEMPTS = 5;

    /**
     * 실패 후 다음 시도까지 대기 분(시도 횟수 순). 마지막 값 이후는 MAX_ATTEMPTS 로 포기
     */
    private const BACKOFF_MINUTES = [1, 5, 30, 120];

    protected $table         = 'webhook_deliveries';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $allowedFields = ['event', 'payload', 'attempts', 'next_attempt_at', 'sent_at', 'failed_at', 'last_error'];

    /**
     * 이벤트를 큐에 올린다. URL 이 없거나 해당 이벤트가 꺼져 있으면 아무것도 하지 않는다.
     * 알림은 부가 기능이라 어떤 실패도 호출한 요청 흐름으로 번지지 않게 한다.
     *
     * @param array<string, scalar|null> $data
     */
    public function enqueue(string $event, string $summary, array $data): void
    {
        try {
            $settings = (new SettingModel())->getAllAsMap();

            if (! isset(self::EVENTS[$event]) || trim((string) ($settings['webhook_url'] ?? '')) === '' || ($settings['webhook_on_' . $event] ?? '1') !== '1') {
                return;
            }

            $text = sprintf('[%s] %s — %s', $settings['site_name'] ?? 'AiCreo', self::EVENTS[$event], $summary);

            $this->insert([
                'event'   => $event,
                'payload' => json_encode([
                    // Slack 은 <, >, & 를 링크·멘션으로 해석하므로 이스케이프한다
                    'text'  => strtr(mb_substr($text, 0, 500), ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']),
                    'event' => $event,
                    'data'  => $data,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
        } catch (Throwable $e) {
            log_message('error', '[Webhook] 큐 적재 실패: ' . $e->getMessage());
        }
    }

    /**
     * 지금 보낼 차례인 미발송 건 (포기한 건 제외).
     *
     * @return list<array<string, mixed>>
     */
    public function due(int $limit): array
    {
        return $this->where('sent_at', null)
            ->where('failed_at', null)
            ->groupStart()
            ->where('next_attempt_at', null)
            ->orWhere('next_attempt_at <=', Time::now()->toDateTimeString())
            ->groupEnd()
            ->orderBy('id', 'ASC')
            ->findAll($limit);
    }

    public function markSent(int $id): void
    {
        $this->update($id, ['sent_at' => Time::now()->toDateTimeString(), 'last_error' => null]);
    }

    /**
     * 실패를 기록한다. 횟수를 다 쓰면 포기(failed_at)하고, 아니면 다음 시도 시각을 미룬다.
     *
     * @param array<string, mixed> $row
     */
    public function markFailed(array $row, string $error): void
    {
        $attempts = (int) $row['attempts'] + 1;
        $update   = ['attempts' => $attempts, 'last_error' => mb_substr($error, 0, 255)];

        if ($attempts >= self::MAX_ATTEMPTS) {
            $update['failed_at'] = Time::now()->toDateTimeString();
        } else {
            $minutes                   = self::BACKOFF_MINUTES[min($attempts, count(self::BACKOFF_MINUTES)) - 1];
            $update['next_attempt_at'] = Time::now()->addMinutes($minutes)->toDateTimeString();
        }

        $this->update((int) $row['id'], $update);
    }

    /**
     * 처리가 끝난(발송·포기) 오래된 행을 지운다.
     */
    public function purgeFinished(int $days): int
    {
        $this->groupStart()->where('sent_at <', Time::now()->subDays($days)->toDateTimeString())
            ->orWhere('failed_at <', Time::now()->subDays($days)->toDateTimeString())->groupEnd()->delete();

        return $this->db->affectedRows();
    }
}
