<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * 두 텍스트의 줄 단위 차이를 LCS 로 계산한다. 글 수정 이력 비교 화면에서 쓴다.
 */
final class LineDiff
{
    /**
     * 두 쪽 줄 수의 곱이 이 값을 넘으면 LCS 표가 너무 커서 통째로 바뀐 것으로 본다
     */
    private const MAX_CELLS = 4_000_000;

    /**
     * @return list<array{type: 'add'|'del'|'same', text: string}>
     */
    public static function diff(string $old, string $new): array
    {
        $a = self::lines($old);
        $b = self::lines($new);
        $n = count($a);
        $m = count($b);

        if ($n * $m > self::MAX_CELLS) {
            return [...self::mark($a, 'del'), ...self::mark($b, 'add')];
        }

        // $lcs[$i][$j] = $a[$i..] 와 $b[$j..] 의 최장 공통 부분 수열 길이
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $rows = [];
        $i    = 0;
        $j    = 0;

        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $rows[] = ['type' => 'same', 'text' => $a[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $rows[] = ['type' => 'del', 'text' => $a[$i++]];
            } else {
                $rows[] = ['type' => 'add', 'text' => $b[$j++]];
            }
        }

        return [...$rows, ...self::mark(array_slice($a, $i), 'del'), ...self::mark(array_slice($b, $j), 'add')];
    }

    /**
     * @return list<string>
     */
    private static function lines(string $text): array
    {
        return $text === '' ? [] : preg_split('/\R/', $text);
    }

    /**
     * @param list<string> $lines
     *
     * @return list<array{type: 'add'|'del'|'same', text: string}>
     */
    private static function mark(array $lines, string $type): array
    {
        return array_map(static fn (string $line): array => ['type' => $type, 'text' => $line], $lines);
    }
}
