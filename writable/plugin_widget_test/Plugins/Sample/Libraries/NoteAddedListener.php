<?php

declare(strict_types=1);

namespace Plugins\Sample\Libraries;

/**
 * `sample.note_added` 구독 예시.
 *
 * 구독자는 놓쳐도 되는 후속 작업만 맡는다. 여기서는 수신한 메모 id 를 기록만 한다.
 */
final class NoteAddedListener
{
    /**
     * @var list<int>
     */
    private static array $received = [];

    /**
     * @param array{note_id: int} $payload
     */
    public static function handle(array $payload): void
    {
        self::$received[] = $payload['note_id'];
    }

    /**
     * @return list<int>
     */
    public static function received(): array
    {
        return self::$received;
    }

    public static function reset(): void
    {
        self::$received = [];
    }
}
