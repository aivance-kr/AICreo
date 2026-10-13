<?php

declare(strict_types=1);

if (! function_exists('post_excerpt')) {
    /**
     * 게시글 HTML 본문에서 목록용 요약문을 만든다 (태그·연속 공백 제거 후 글자 수로 자름).
     */
    function post_excerpt(?string $html, int $length = 120): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($text) <= $length ? $text : rtrim(mb_substr($text, 0, $length)) . '…';
    }
}

if (! function_exists('post_read_minutes')) {
    /**
     * 본문 글자 수로 읽는 시간을 분 단위로 추정한다 (한국어 분당 약 500자, 최소 1분).
     */
    function post_read_minutes(?string $html): int
    {
        $text = trim((string) preg_replace('/\s+/u', '', strip_tags((string) $html)));

        return max(1, (int) ceil(mb_strlen($text) / 500));
    }
}
