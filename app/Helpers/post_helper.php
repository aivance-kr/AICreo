<?php

declare(strict_types=1);
use App\Libraries\HtmlSanitizer;

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

if (! function_exists('post_content_html')) {
    /**
     * 게시글 본문의 업로드 파일 경로를 루트 기준 절대 경로(/uploads/...)로 보정한다.
     *
     * 에디터가 이미지를 상대 경로(../../uploads/...)로 저장하던 시절의 글은 /index.php/ 주소에서
     * 열면 /index.php/uploads/... 로 해석돼 이미지가 깨진다. src·href 속성만 대상으로 한다.
     * 출력 직전에 허용목록 정화도 해, 과거에 저장된 위험한 HTML 도 무력화한다.
     */
    function post_content_html(?string $html): string
    {
        return (string) preg_replace(
            '~(\s(?:src|href)=["\']?)(?:/?(?:\.\./|\./)*)(?:index\.php/)?(uploads/)~i',
            '$1/$2',
            HtmlSanitizer::clean($html),
        );
    }
}
