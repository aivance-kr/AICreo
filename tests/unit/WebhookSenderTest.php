<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\WebhookSender;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

/**
 * @internal
 */
final class WebhookSenderTest extends CIUnitTestCase
{
    public function testSuccessReturnsNullAndSendsTimeoutAndJson(): void
    {
        $seen   = [];
        $sender = new WebhookSender(static function (string $url, array $options) use (&$seen): int {
            $seen = [$url, $options];

            return 204;
        });

        $this->assertNull($sender->send('https://hooks.example/x', ['text' => 'hi']));
        $this->assertSame('https://hooks.example/x', $seen[0]);
        $this->assertSame(['text' => 'hi'], $seen[1]['json']);
        $this->assertSame(5, $seen[1]['timeout']);
    }

    public function testNon2xxIsFailure(): void
    {
        $sender = new WebhookSender(static fn (): int => 500);

        $this->assertSame('http 500', $sender->send('https://hooks.example/x', []));
    }

    public function testExceptionMessageHasUrlStripped(): void
    {
        $sender = new WebhookSender(static fn (): int => throw new RuntimeException('failed https://hooks.example/SECRET-TOKEN now'));
        $error  = (string) $sender->send('https://hooks.example/SECRET-TOKEN', []);

        $this->assertStringNotContainsString('SECRET-TOKEN', $error);
    }

    public function testRejectsNonHttpUrlWithoutCalling(): void
    {
        $sender = new WebhookSender(static fn (): int => throw new RuntimeException('호출되면 안 됨'));

        $this->assertSame('invalid url', $sender->send('file:///etc/passwd', []));
    }
}
