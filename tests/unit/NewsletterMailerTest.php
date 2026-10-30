<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\NewsletterMailer;
use CodeIgniter\Email\Email;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class NewsletterMailerTest extends CIUnitTestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $sent = [];

    private function mailer(bool $succeed = true): NewsletterMailer
    {
        $test = $this;

        return new NewsletterMailer(static function () use ($test, $succeed): Email {
            return new class ($test, $succeed) extends Email {
                public function __construct(private readonly NewsletterMailerTest $test, private readonly bool $succeed)
                {
                    parent::__construct(config('Email'));
                }

                public function send($autoClear = true)
                {
                    $this->test->capture(['headers' => $this->headers, 'body' => $this->body]);

                    return $this->succeed;
                }
            };
        });
    }

    /**
     * @param array<string, mixed> $mail
     */
    public function capture(array $mail): void
    {
        $this->sent = $mail;
    }

    public function testPostMailHasUnsubscribeLinkHeadersAndPrivacyNotice(): void
    {
        $ok = $this->mailer()->sendPost([
            'email'             => 'a@example.com',
            'unsubscribe_token' => 'tok123',
            'post_id'           => 7,
            'title'             => '제목 <b>',
            'content'           => '<p>본문 <script>x</script></p>',
            'board_slug'        => 'blog',
        ]);

        $this->assertTrue($ok);
        $this->assertStringContainsString('newsletter/unsubscribe/tok123', $this->sent['headers']['List-Unsubscribe']);
        $this->assertSame('List-Unsubscribe=One-Click', $this->sent['headers']['List-Unsubscribe-Post']);
        $this->assertStringContainsString('board/blog/7', $this->sent['body']);
        $this->assertStringContainsString('구독 해지', $this->sent['body']);
        $this->assertStringContainsString('이메일 주소 하나', $this->sent['body']);
        $this->assertStringNotContainsString('<b>', $this->sent['body']);
    }

    public function testConfirmationMailLinksToConfirmUrl(): void
    {
        $this->mailer()->sendConfirmation(['email' => 'a@example.com', 'confirm_token' => 'conf1', 'unsubscribe_token' => 'unsub1']);

        $this->assertStringContainsString('newsletter/confirm/conf1', $this->sent['body']);
    }

    public function testSendFailureReturnsFalse(): void
    {
        $this->assertFalse($this->mailer(false)->sendConfirmation(['email' => 'a@example.com', 'confirm_token' => 'c', 'unsubscribe_token' => 'u']));
    }
}
