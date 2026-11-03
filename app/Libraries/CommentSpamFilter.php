<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * 댓글 본문의 스팸 의심 여부를 판단한다 (링크 개수 초과, 금칙어 포함).
 * 의심 댓글은 거절하지 않고 승인 대기로 보내 오탐을 관리자가 구제한다.
 */
final class CommentSpamFilter
{
    public const MAX_LINKS = 2;

    /**
     * 기본 금칙어 (소문자 비교)
     */
    public const DEFAULT_BANNED_WORDS = ['카지노', '바카라', '토토사이트', '대출상담', 'viagra', 'casino', 'porn'];

    /**
     * @var list<string>
     */
    private array $bannedWords;

    /**
     * @param list<string> $bannedWords
     */
    public function __construct(array $bannedWords = self::DEFAULT_BANNED_WORDS)
    {
        $words = array_map(static fn (string $w): string => mb_strtolower(trim($w)), $bannedWords);

        $this->bannedWords = array_values(array_filter($words, static fn (string $w): bool => $w !== ''));
    }

    public function isSuspicious(string $content): bool
    {
        return $this->linkCount($content) > self::MAX_LINKS || $this->containsBannedWord($content);
    }

    private function linkCount(string $content): int
    {
        return (int) preg_match_all('~(?:https?://|www\.)~i', $content);
    }

    private function containsBannedWord(string $content): bool
    {
        $lower = mb_strtolower($content);

        foreach ($this->bannedWords as $word) {
            if (str_contains($lower, $word)) {
                return true;
            }
        }

        return false;
    }
}
