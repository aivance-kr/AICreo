<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Libraries\NewsletterMailer;
use App\Models\SubscriberModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 뉴스레터 구독 — 신청(POST), 이메일 확인 링크, 원클릭 해지 링크.
 */
class NewsletterController extends BaseController
{
    /**
     * 허니팟 입력 이름 — 사람에게는 보이지 않으니 값이 있으면 봇이다
     */
    public const HONEYPOT_FIELD = 'website';

    private const IP_LIMIT    = 5;
    private const EMAIL_LIMIT = 2;

    public function subscribe(): ResponseInterface
    {
        $done = redirect()->back()->with('success', '확인 메일을 보냈습니다. 메일의 링크를 눌러야 구독이 완료됩니다.');

        // 봇에게는 성공한 것처럼 보이게 하고 아무것도 하지 않는다
        if ((string) $this->request->getPost(self::HONEYPOT_FIELD) !== '') {
            return $done;
        }

        $email = mb_strtolower(trim((string) $this->request->getPost('email')));
        if (! $this->validateData(['email' => $email], ['email' => 'required|max_length[190]|valid_email'])) {
            return redirect()->back()->with('error', '올바른 이메일 주소를 입력해 주세요.');
        }

        // 같은 IP 의 반복 신청, 한 주소로 확인 메일을 퍼붓는 것을 모두 막는다
        $throttler = service('throttler');
        if (! $throttler->check('newsletter_ip_' . md5($this->request->getIPAddress()), self::IP_LIMIT, HOUR)
            || ! $throttler->check('newsletter_email_' . md5($email), self::EMAIL_LIMIT, HOUR)) {
            return redirect()->back()->with('error', '요청이 너무 많습니다. 잠시 후 다시 시도해 주세요.');
        }

        // 이미 구독 중인 주소인지 응답으로 드러내지 않는다(null 이어도 같은 메시지)
        $subscriber = (new SubscriberModel())->subscribe($email);
        if ($subscriber !== null) {
            (new NewsletterMailer())->sendConfirmation($subscriber);
        }

        return $done;
    }

    public function confirm(string $token): string
    {
        $ok = (new SubscriberModel())->confirm($token);

        return $this->result(
            $ok,
            '구독이 완료되었습니다',
            '이제 새 글이 공개되면 메일로 알려 드립니다. 언제든 메일 하단의 링크로 해지할 수 있습니다.',
            '유효하지 않은 링크입니다',
            '이미 해지했거나 만료된 확인 링크입니다. 구독 폼에서 다시 신청해 주세요.',
        );
    }

    /**
     * 링크를 열면 바로 해지한다(원클릭). 메일 앱의 List-Unsubscribe-Post 요청도 같은 주소로 들어온다.
     */
    public function unsubscribe(string $token): string
    {
        $ok = (new SubscriberModel())->unsubscribe($token);

        return $this->result(
            $ok,
            '구독을 해지했습니다',
            '더 이상 뉴스레터를 보내지 않습니다.',
            '유효하지 않은 링크입니다',
            '해지 링크를 확인해 주세요.',
        );
    }

    private function result(bool $ok, string $okTitle, string $okMessage, string $failTitle, string $failMessage): string
    {
        return $this->render('newsletter/result', [
            'ok'      => $ok,
            'heading' => $ok ? $okTitle : $failTitle,
            'message' => $ok ? $okMessage : $failMessage,
            'page'    => ['title' => '뉴스레터', 'meta_desc' => '뉴스레터 구독', 'noindex' => true],
        ]);
    }
}
