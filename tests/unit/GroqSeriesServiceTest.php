<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\Ai\GroqSeriesService;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

/**
 * @internal
 */
final class GroqSeriesServiceTest extends CIUnitTestCase
{
    /**
     * @var list<array{id: int, title: string, description: string|null, samples: list<string>}>
     */
    private array $candidates = [
        ['id' => 1, 'title' => 'Claude Code 실전기', 'description' => 'AI 코딩 도구 사용기', 'samples' => ['dev-issue 스킬', '토큰 사용량']],
        ['id' => 2, 'title' => 'AI가 만든 게시판', 'description' => null, 'samples' => []],
    ];

    private function reply(string $content): string
    {
        return (string) json_encode(['choices' => [['message' => ['content' => $content]]]]);
    }

    public function testDisabledWithoutKey(): void
    {
        $svc = new GroqSeriesService('', null, static fn (): string => throw new RuntimeException('호출되면 안 됨'));

        $this->assertFalse($svc->isEnabled());
        $this->assertSame([], $svc->suggest('제목', '<p>본문</p>', $this->candidates));
    }

    public function testNoCandidatesSkipsCall(): void
    {
        $svc = new GroqSeriesService('k', 'm', static fn (): string => throw new RuntimeException('호출되면 안 됨'));

        $this->assertSame([], $svc->suggest('제목', '본문', []));
    }

    public function testKeepsOnlyKnownIdsWithoutDuplicates(): void
    {
        $svc = new GroqSeriesService('k', 'm', fn (): string => $this->reply('{"series_ids":[2,"1",99,2,"x",1.5,null]}'));

        $this->assertSame([2, 1], $svc->suggest('제목', '<p>본문</p>', $this->candidates));
    }

    public function testEmptyAnswerMeansNoSeries(): void
    {
        $svc = new GroqSeriesService('k', 'm', fn (): string => $this->reply('{"series_ids":[]}'));

        $this->assertSame([], $svc->suggest('제목', '본문', $this->candidates));
    }

    public function testSendsCandidatesAndPlainTextWithAuthHeader(): void
    {
        $captured = [];
        $svc      = new GroqSeriesService('secret', 'model-x', function (string $url, array $opt) use (&$captured): string {
            $captured = $opt;

            return $this->reply('{"series_ids":[1]}');
        });

        $svc->suggest('토큰 이야기', '<p>안녕 <b>세상</b></p><script>alert(1)</script>', $this->candidates);

        $user = $captured['json']['messages'][1]['content'];
        $this->assertSame('Bearer secret', $captured['headers']['Authorization']);
        $this->assertSame('model-x', $captured['json']['model']);
        $this->assertStringContainsString('id 1: Claude Code 실전기', $user);
        $this->assertStringContainsString('최근 글: dev-issue 스킬 / 토큰 사용량', $user);
        $this->assertStringContainsString('안녕 세상', $user);
        $this->assertStringNotContainsString('<b>', $user);
    }

    public function testApiFailureAndBadFormatReturnEmpty(): void
    {
        $failing = new GroqSeriesService('k', null, static fn (): string => throw new RuntimeException('timeout'));
        $this->assertSame([], $failing->suggest('제목', '본문', $this->candidates));

        $broken = new GroqSeriesService('k', null, fn (): string => $this->reply('not json'));
        $this->assertSame([], $broken->suggest('제목', '본문', $this->candidates));
    }
}
