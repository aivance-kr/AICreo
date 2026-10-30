<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\Ai\GroqPostAssistService;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

/**
 * @internal
 */
final class GroqPostAssistServiceTest extends CIUnitTestCase
{
    private function reply(string $content): string
    {
        return (string) json_encode(['choices' => [['message' => ['content' => $content]]]]);
    }

    public function testDisabledWithoutKey(): void
    {
        $svc = new GroqPostAssistService('', null, static fn (): string => throw new RuntimeException('호출되면 안 됨'));

        $this->assertFalse($svc->isEnabled());
        $this->assertNull($svc->suggest('제목', '<p>본문</p>'));
    }

    public function testEmptyInputSkipsCall(): void
    {
        $svc = new GroqPostAssistService('k', 'm', static fn (): string => throw new RuntimeException('호출되면 안 됨'));

        $this->assertNull($svc->suggest('', '<p> </p>'));
    }

    public function testReturnsValidatedSuggestion(): void
    {
        $json = (string) json_encode([
            'summary' => '요약 문장입니다.',
            'tags'    => ['PHP', ' CodeIgniter ', 'php', 'a,b', '#해시', 7, ''],
            'titles'  => ['제목 후보 1', '제목 후보 2'],
        ]);
        $svc = new GroqPostAssistService('k', 'm', fn (): string => $this->reply($json));

        $this->assertSame([
            'summary' => '요약 문장입니다.',
            'tags'    => ['PHP', 'CodeIgniter', 'ab', '해시'],
            'titles'  => ['제목 후보 1', '제목 후보 2'],
        ], $svc->suggest('제목', '<p>본문</p>'));
    }

    public function testLimitsLengthsAndCounts(): void
    {
        $json = (string) json_encode([
            'summary' => str_repeat('가', 400),
            'tags'    => ['t1', 't2', 't3', 't4', 't5', 't6', str_repeat('나', 50)],
            'titles'  => ['a', 'b', 'c', 'd', str_repeat('다', 300)],
        ]);
        $svc    = new GroqPostAssistService('k', 'm', fn (): string => $this->reply($json));
        $result = $svc->suggest('제목', '본문');

        $this->assertNotNull($result);
        $this->assertSame(160, mb_strlen($result['summary']));
        $this->assertSame(['t1', 't2', 't3', 't4', 't5'], $result['tags']);
        $this->assertSame(['a', 'b', 'c'], $result['titles']);
    }

    public function testStripsMarkup(): void
    {
        $json = (string) json_encode([
            'summary' => '<script>alert(1)</script>안전한 <b>요약</b>',
            'tags'    => ['<img src=x onerror=1>태그'],
            'titles'  => ['<i>제목</i>'],
        ]);
        $svc = new GroqPostAssistService('k', 'm', fn (): string => $this->reply($json));

        $this->assertSame([
            'summary' => 'alert(1)안전한 요약',
            'tags'    => ['태그'],
            'titles'  => ['제목'],
        ], $svc->suggest('제목', '본문'));
    }

    public function testSendsPlainTextWithAuthHeaderAndShortTimeout(): void
    {
        $captured = [];
        $svc      = new GroqPostAssistService('secret', 'model-x', function (string $url, array $opt) use (&$captured): string {
            $captured = ['url' => $url, 'opt' => $opt];

            return $this->reply('{"summary":"s","tags":[],"titles":[]}');
        });

        $svc->suggest('내 제목', '<p>안녕 &amp; <b>반가워</b></p>');

        $this->assertStringContainsString('groq.com', $captured['url']);
        $this->assertSame(5, $captured['opt']['timeout']);
        $this->assertSame('Bearer secret', $captured['opt']['headers']['Authorization']);
        $this->assertSame('model-x', $captured['opt']['json']['model']);
        $user = $captured['opt']['json']['messages'][1]['content'];
        $this->assertStringContainsString('내 제목', $user);
        $this->assertStringContainsString('안녕 & 반가워', $user);
        $this->assertStringNotContainsString('<b>', $user);
    }

    public function testFailuresReturnNull(): void
    {
        $failing = new GroqPostAssistService('k', null, static fn (): string => throw new RuntimeException('timeout'));
        $broken  = new GroqPostAssistService('k', null, fn (): string => $this->reply('not json'));
        $empty   = new GroqPostAssistService('k', null, fn (): string => $this->reply('{"summary":"","tags":[],"titles":[]}'));

        $this->assertNull($failing->suggest('제목', '본문'));
        $this->assertNull($broken->suggest('제목', '본문'));
        $this->assertNull($empty->suggest('제목', '본문'));
    }
}
