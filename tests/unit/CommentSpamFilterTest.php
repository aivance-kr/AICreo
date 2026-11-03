<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\CommentSpamFilter;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CommentSpamFilterTest extends CIUnitTestCase
{
    public function testPlainCommentIsClean(): void
    {
        $this->assertFalse((new CommentSpamFilter())->isSuspicious('좋은 글 감사합니다.'));
    }

    public function testLinksUpToLimitAreAllowed(): void
    {
        $content = '참고: https://a.example 그리고 http://b.example';

        $this->assertFalse((new CommentSpamFilter())->isSuspicious($content));
    }

    public function testTooManyLinksAreSuspicious(): void
    {
        $content = 'https://a.example http://b.example www.c.example';

        $this->assertTrue((new CommentSpamFilter())->isSuspicious($content));
    }

    public function testBannedWordIsSuspiciousCaseInsensitive(): void
    {
        $filter = new CommentSpamFilter(['카지노', 'viagra']);

        $this->assertTrue($filter->isSuspicious('온라인 카지노 추천'));
        $this->assertTrue($filter->isSuspicious('Buy VIAGRA now'));
        $this->assertFalse($filter->isSuspicious('평범한 댓글'));
    }

    public function testEmptyBannedWordEntriesAreIgnored(): void
    {
        $this->assertFalse((new CommentSpamFilter(['', ' ']))->isSuspicious('아무 말'));
    }
}
