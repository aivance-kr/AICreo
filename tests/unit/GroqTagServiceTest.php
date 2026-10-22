<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\Ai\GroqTagService;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

/**
 * @internal
 */
final class GroqTagServiceTest extends CIUnitTestCase
{
    private function reply(string $content): string
    {
        return (string) json_encode(['choices' => [['message' => ['content' => $content]]]]);
    }

    public function testDisabledWithoutKey(): void
    {
        $svc = new GroqTagService('', null, static fn (): string => throw new RuntimeException('호출되면 안 됨'));

        $this->assertFalse($svc->isEnabled());
        $this->assertSame([], $svc->extract('제목', '<p>본문</p>'));
    }

    public function testExtractsNormalizesAndLimitsTags(): void
    {
        $json = '{"tags":["#PHP"," 코드이그나이터 ","php","a,b","","c","d","e","f"]}';
        $svc  = new GroqTagService('k', 'm', fn (): string => $this->reply($json));

        $this->assertSame(['PHP', '코드이그나이터', 'a b', 'c', 'd'], $svc->extract('제목', '<p>본문</p>'));
    }

    public function testSendsPlainTextAndAuthHeader(): void
    {
        $captured = [];
        $svc      = new GroqTagService('secret', 'model-x', function (string $url, array $opt) use (&$captured): string {
            $captured = $opt;

            return $this->reply('{"tags":["x"]}');
        });

        $svc->extract('제목', '<p>안녕 <b>세상</b></p><script>alert(1)</script>');

        $this->assertSame('Bearer secret', $captured['headers']['Authorization']);
        $this->assertSame('model-x', $captured['json']['model']);
        $this->assertStringContainsString('안녕 세상', $captured['json']['messages'][1]['content']);
        $this->assertStringNotContainsString('<b>', $captured['json']['messages'][1]['content']);
    }

    public function testApiFailureReturnsEmpty(): void
    {
        $svc = new GroqTagService('k', null, static fn (): string => throw new RuntimeException('timeout'));

        $this->assertSame([], $svc->extract('제목', '본문'));
    }

    public function testMalformedReplyReturnsEmpty(): void
    {
        foreach (['', 'not json', $this->reply('not json'), $this->reply('{"tags":"x"}')] as $body) {
            $svc = new GroqTagService('k', null, static fn (): string => $body);
            $this->assertSame([], $svc->extract('제목', '본문'));
        }
    }
}
