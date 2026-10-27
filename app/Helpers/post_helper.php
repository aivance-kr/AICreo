<?php

declare(strict_types=1);
use App\Libraries\HtmlSanitizer;

if (! function_exists('post_excerpt')) {
    /**
     * 게시글 HTML 본문에서 목록용 요약문을 만든다 (태그·연속 공백 제거 후 글자 수로 자름).
     */
    function post_excerpt(?string $html, int $length = 120): string
    {
        // 엔티티를 푼 뒤 다시 태그를 걷어낸다 — &lt;script&gt; 가 풀려 마크업으로 되살아나면
        // RSS 처럼 설명을 HTML 로 해석하는 소비자에서 XSS 가 된다
        $text = strip_tags(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
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
     * 마크다운 굵게(**글**)로 쓴 글자는 <strong> 으로 바꾼다.
     */
    function post_content_html(?string $html): string
    {
        return (string) preg_replace(
            '~(\s(?:src|href)=["\']?)(?:/?(?:\.\./|\./)*)(?:index\.php/)?(uploads/)~i',
            '$1/$2',
            post_markdown_bold(HtmlSanitizer::clean($html)),
        );
    }
}

if (! function_exists('post_markdown_bold')) {
    /**
     * 정화된 HTML 의 본문 텍스트에서 마크다운 굵게(**글**)를 <strong> 으로 바꾼다.
     * 태그 속성과 pre·code 안(코드 예시)은 건드리지 않는다.
     */
    function post_markdown_bold(string $html): string
    {
        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }

        $codeDepth = 0;

        foreach ($parts as $i => $part) {
            if (preg_match('~^<(/?)(?:pre|code)[\s>]~i', $part, $m) === 1) {
                $codeDepth += $m[1] === '/' ? -1 : 1;
            } elseif ($codeDepth <= 0 && ($part === '' || $part[0] !== '<')) {
                $parts[$i] = (string) preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/u', '<strong>$1</strong>', $part);
            }
        }

        return implode('', $parts);
    }
}
