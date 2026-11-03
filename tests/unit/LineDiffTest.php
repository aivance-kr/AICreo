<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\LineDiff;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class LineDiffTest extends CIUnitTestCase
{
    public function testIdenticalTextIsAllSame(): void
    {
        $rows = LineDiff::diff("a\nb", "a\nb");

        $this->assertSame([['type' => 'same', 'text' => 'a'], ['type' => 'same', 'text' => 'b']], $rows);
    }

    public function testMarksRemovedAndAddedLines(): void
    {
        $rows = LineDiff::diff("a\nb\nc", "a\nx\nc");

        $this->assertSame([
            ['type' => 'same', 'text' => 'a'],
            ['type' => 'del', 'text' => 'b'],
            ['type' => 'add', 'text' => 'x'],
            ['type' => 'same', 'text' => 'c'],
        ], $rows);
    }

    public function testEmptySideIsAllAddedOrRemoved(): void
    {
        $this->assertSame([['type' => 'add', 'text' => 'a']], LineDiff::diff('', 'a'));
        $this->assertSame([['type' => 'del', 'text' => 'a']], LineDiff::diff('a', ''));
        $this->assertSame([], LineDiff::diff('', ''));
    }

    public function testNormalizesCrLf(): void
    {
        $this->assertSame([['type' => 'same', 'text' => 'a'], ['type' => 'same', 'text' => 'b']], LineDiff::diff("a\r\nb", "a\nb"));
    }

    public function testHugeInputFallsBackToWholeReplace(): void
    {
        $old = implode("\n", range(1, 3000));
        $new = implode("\n", range(3001, 6000));

        $rows = LineDiff::diff($old, $new);

        $this->assertCount(6000, $rows);
        $this->assertSame('del', $rows[0]['type']);
        $this->assertSame('add', $rows[3000]['type']);
    }
}
