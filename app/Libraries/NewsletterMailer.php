<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\SettingModel;
use Closure;
use CodeIgniter\Config\Services;
use CodeIgniter\Email\Email;
use Throwable;

/**
 * 뉴스레터 메일 작성·발송 — 확인 메일과 새 글 알림.
 * 실패는 예외 대신 false 로 돌려주고 로그를 남긴다(호출부가 재시도 여부를 정한다).
 */
final class NewsletterMailer
{
    private const EXCERPT_LENGTH = 200;

    /**
     * 구독 폼·메일 하단의 개인정보 안내 문구.
     */
    public static function privacyNotice(?string $locale = null): string
    {
        return lang('Newsletter.privacyNotice', [], $locale);
    }

    /**
     * @param Closure(): Email|null $emailFactory 메일 객체 공급자 — 테스트에서 가짜로 바꾼다
     */
    public function __construct(private readonly ?Closure $emailFactory = null)
    {
    }

    /**
     * @param array<string, mixed> $subscriber
     * @param string|null          $locale     메일 언어(신청 시점의 요청 언어). null 이면 현재 요청 언어
     */
    public function sendConfirmation(array $subscriber, ?string $locale = null): bool
    {
        $site = $this->siteName();
        $url  = base_url('newsletter/confirm/' . $subscriber['confirm_token']);
        $body = '<p>' . esc(lang('Newsletter.mailConfirmIntro', [$site], $locale)) . '</p>'
            . '<p>' . esc(lang('Newsletter.mailConfirmGuide', [], $locale)) . '</p>'
            . '<p><a href="' . htmlspecialchars($url, ENT_QUOTES) . '">' . esc(lang('Newsletter.mailConfirmLink', [], $locale)) . '</a></p>'
            . $this->footer((string) $subscriber['unsubscribe_token'], $locale);

        return $this->send((string) $subscriber['email'], lang('Newsletter.mailConfirmSubject', [$site], $locale), $body, (string) $subscriber['unsubscribe_token']);
    }

    /**
     * @param array<string, mixed> $delivery SubscriberModel::pendingDeliveries() 한 행
     */
    public function sendPost(array $delivery): bool
    {
        // 글의 언어로 보낸다(큐 워커는 CLI 라 요청 언어가 없다). 비어 있으면 기본 언어
        $locale  = isset($delivery['locale']) && $delivery['locale'] !== '' ? (string) $delivery['locale'] : null;
        $site    = $this->siteName();
        $postUrl = base_url('board/' . $delivery['board_slug'] . '/' . $delivery['post_id']);
        $excerpt = mb_strimwidth(trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) preg_replace('#<(script|style)\b.*?</\1>#is', '', (string) $delivery['content'])))), 0, self::EXCERPT_LENGTH, '…');
        $body    = '<h2>' . esc((string) $delivery['title']) . '</h2>'
            . ($excerpt !== '' ? '<p>' . esc($excerpt) . '</p>' : '')
            . '<p><a href="' . htmlspecialchars($postUrl, ENT_QUOTES) . '">' . esc(lang('Newsletter.mailReadPost', [], $locale)) . '</a></p>'
            . $this->footer((string) $delivery['unsubscribe_token'], $locale);

        return $this->send((string) $delivery['email'], '[' . $site . '] ' . $delivery['title'], $body, (string) $delivery['unsubscribe_token']);
    }

    private function footer(string $unsubscribeToken, ?string $locale): string
    {
        return '<hr><p style="color:#666;font-size:12px">' . esc(self::privacyNotice($locale))
            . '<br><a href="' . htmlspecialchars(base_url('newsletter/unsubscribe/' . $unsubscribeToken), ENT_QUOTES) . '">' . esc(lang('Newsletter.mailUnsubscribe', [], $locale)) . '</a></p>';
    }

    private function send(string $to, string $subject, string $htmlBody, string $unsubscribeToken): bool
    {
        try {
            // 공유 인스턴스는 이전 수신자 상태가 남을 수 있어 매번 새로 만든다
            $email = $this->emailFactory !== null ? ($this->emailFactory)() : Services::email(null, false);
            $from  = (string) config('Email')->fromEmail;
            if ($from === '') {
                $from = (string) ((new SettingModel())->getAllAsMap()['email'] ?? '');
            }
            if ($from !== '') {
                $email->setFrom($from, $this->siteName());
            }

            $unsubscribeUrl = base_url('newsletter/unsubscribe/' . $unsubscribeToken);
            $email->setMailType('html');
            $email->setTo($to);
            $email->setSubject($subject);
            $email->setMessage($htmlBody);
            // 메일 앱의 원클릭 해지(RFC 8058)
            $email->setHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
            $email->setHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

            if (! $email->send(false)) {
                log_message('error', '[Newsletter] 발송 실패 to=' . self::mask($to));

                return false;
            }

            return true;
        } catch (Throwable $e) {
            log_message('error', '[Newsletter] 발송 예외 to=' . self::mask($to) . ' ' . $e::class);

            return false;
        }
    }

    private static function mask(string $email): string
    {
        return (string) preg_replace('/^(.).*(@.*)$/u', '$1***$2', $email);
    }

    private function siteName(): string
    {
        return (string) ((new SettingModel())->getAllAsMap()['site_name'] ?? 'AiCreo');
    }
}
