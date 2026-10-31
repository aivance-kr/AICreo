<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\Ai\GroqRateLimitedException;
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

    public function testReportsReasonWhenNoTagsCouldBeExtracted(): void
    {
        $disabled = new GroqTagService('', null, static fn (): string => throw new RuntimeException('호출되면 안 됨'));
        $disabled->extract('제목', '<p>본문</p>');
        $this->assertSame('GROQ_API_KEY 미설정', $disabled->lastError());

        $emptyTags = new GroqTagService('k', 'm', fn (): string => $this->reply('{"tags":[]}'));
        $this->assertSame([], $emptyTags->extract('제목', '<p>본문</p>'));
        $this->assertSame('모델이 태그를 하나도 돌려주지 않음', $emptyTags->lastError());

        $badContent = new GroqTagService('k', 'm', fn (): string => $this->reply('태그는 없습니다'));
        $this->assertSame([], $badContent->extract('제목', '<p>본문</p>'));
        $this->assertSame('content 가 {"tags":[...]} 형식이 아님', $badContent->lastError());

        $notJson = new GroqTagService('k', 'm', static fn (): string => 'oops');
        $this->assertSame([], $notJson->extract('제목', '<p>본문</p>'));
        $this->assertSame('응답이 JSON 이 아님', $notJson->lastError());

        $thrown = new GroqTagService('k', 'm', static fn (): string => throw new RuntimeException('시간 초과'));
        $this->assertSame([], $thrown->extract('제목', '<p>본문</p>'));
        $this->assertSame('호출 예외: 시간 초과', $thrown->lastError());
    }

    public function testLastErrorIsClearedAfterSuccess(): void
    {
        $replies = ['{"tags":[]}', '{"tags":["PHP"]}'];
        $svc     = new GroqTagService('k', 'm', function () use (&$replies): string {
            return $this->reply((string) array_shift($replies));
        });

        $svc->extract('제목', '<p>본문</p>');
        $this->assertNotNull($svc->lastError());

        $this->assertSame(['PHP'], $svc->extract('제목', '<p>본문</p>'));
        $this->assertNull($svc->lastError());
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

    public function testRateLimitExposesRetryAfter(): void
    {
        $svc = new GroqTagService('k', null, static fn (): string => throw new GroqRateLimitedException(7));

        $this->assertSame([], $svc->extract('제목', '본문'));
        $this->assertSame('HTTP 429', $svc->lastError());
        $this->assertSame(7, $svc->retryAfter());

        $noHeader = new GroqTagService('k', null, static fn (): string => throw new GroqRateLimitedException());
        $noHeader->extract('제목', '본문');
        $this->assertSame(0, $noHeader->retryAfter());

        $ok = new GroqTagService('k', null, fn (): string => $this->reply('{"tags":["a"]}'));
        $ok->extract('제목', '본문');
        $this->assertNull($ok->retryAfter());
    }

    public function testMalformedReplyReturnsEmpty(): void
    {
        foreach (['', 'not json', $this->reply('not json'), $this->reply('{"tags":"x"}')] as $body) {
            $svc = new GroqTagService('k', null, static fn (): string => $body);
            $this->assertSame([], $svc->extract('제목', '본문'));
        }
    }
}
