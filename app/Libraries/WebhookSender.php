<?php

declare(strict_types=1);

namespace App\Libraries;

use Closure;
use CodeIgniter\Config\Services;
use Throwable;

/**
 * 웹훅 URL 로 JSON 을 POST 한다. 성공이면 null, 실패면 (URL 이 지워진) 짧은 사유를 돌려준다.
 * Slack Incoming Webhook 도 그대로 받도록 `text` 를 최상위에 둔다.
 */
final class WebhookSender
{
    private const TIMEOUT         = 5;
    private const CONNECT_TIMEOUT = 3;

    /**
     * @param Closure(string, array<string, mixed>): int|null $http 테스트용 HTTP 대체 (url, 옵션 → 상태 코드)
     */
    public function __construct(private readonly ?Closure $http = null)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function send(string $url, array $payload): ?string
    {
        if (! preg_match('#^https?://#i', $url)) {
            return 'invalid url';
        }

        $options = [
            'json'            => $payload,
            'timeout'         => self::TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'http_errors'     => false,
        ];

        try {
            $status = $this->http !== null
                ? ($this->http)($url, $options)
                // 공유 인스턴스는 이전 호출의 옵션을 들고 있을 수 있어 새로 만든다
                : Services::curlrequest([], null, null, false)->post($url, $options)->getStatusCode();
        } catch (Throwable $e) {
            return mb_substr((string) preg_replace('#https?://\S+#i', '[url]', $e->getMessage()), 0, 200);
        }

        return $status >= 200 && $status < 300 ? null : 'http ' . $status;
    }
}
