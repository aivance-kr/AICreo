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
     * 마크다운으로 쓴 글(코드 블록·표·제목·목록·인용·구분선·굵게·기울임·코드·링크)은 HTML 태그로 바꾼다.
     */
    function post_content_html(?string $html): string
    {
        return (string) preg_replace(
            '~(\s(?:src|href)=["\']?)(?:/?(?:\.\./|\./)*)(?:index\.php/)?(uploads/)~i',
            '$1/$2',
            post_markdown_inline(post_markdown_blocks(post_markdown_headings(post_markdown_tables(post_markdown_fences(HtmlSanitizer::clean($html)))))),
        );
    }
}

if (! function_exists('post_markdown_fences')) {
    /**
     * 정화된 HTML 에서 ``` 로 감싼 코드 블록(여러 <p> 에 걸쳐도 됨)을 <pre><code> 로 바꾼다.
     * 여는 ``` 는 문단 시작, 닫는 ``` 는 문단 끝이어야 하며, 언어(```bash)는 class 로 남긴다.
     * 다른 마크다운 변환이 코드 안을 건드리지 않도록 가장 먼저 실행한다.
     */
    function post_markdown_fences(string $html): string
    {
        return preg_replace_callback(
            '~<p>\s*```([A-Za-z0-9+-]*)(?:\s*<br\s*/?>|\s|(?=</p>))(.*?)\s*```\s*</p>~su',
            static function (array $m): string {
                $code = preg_replace(['~\s*<br\s*/?>\s*~i', '~</p>\s*<p>~i'], "\n", $m[2]) ?? $m[2];
                $code = strip_tags(str_replace('&nbsp;', ' ', $code));
                $code = trim((string) preg_replace('/[ \t\x{A0}]+$/mu', '', $code), "\n");
                $class = $m[1] === '' ? '' : ' class="language-' . strtolower($m[1]) . '"';

                return "<pre{$class}><code{$class}>{$code}</code></pre>";
            },
            $html,
        ) ?? $html;
    }
}

if (! function_exists('post_markdown_tables')) {
    /**
     * 정화된 HTML 에서 마크다운 표(| 로 시작·끝나는 줄 + 둘째 줄 |---|---|)를 <table> 로 바꾼다.
     * 줄은 한 <p> 안의 <br> 이든 연속한 <p> 이든 모아서 본다. 첫 줄은 <th>, 나머지는 <td> 이며
     * 열 수는 머리글에 맞춘다. 형식이 어긋나면 원문을 그대로 둔다. pre·code 안은 <p> 가 아니라 건드리지 않는다.
     */
    function post_markdown_tables(string $html): string
    {
        return preg_replace_callback(
            '~(?:<p>\s*\|(?:(?!</p>).)*\|\s*</p>\s*)+~su',
            static function (array $m): string {
                $lines = [];
                foreach (preg_split('~\s*</p>\s*<p>\s*|\s*<br\s*/?>\s*~i', trim((string) preg_replace('~^<p>|</p>$~i', '', trim($m[0])))) ?: [] as $line) {
                    $lines[] = trim(str_replace('&nbsp;', ' ', $line));
                }

                $sep = '/^\|\s*:?-{3,}:?\s*(?:\|\s*:?-{3,}:?\s*)*\|$/';
                if (count($lines) < 2 || preg_match($sep, $lines[1]) !== 1) {
                    return $m[0];
                }

                $rows = [];
                foreach (array_merge([$lines[0]], array_slice($lines, 2)) as $line) {
                    if (preg_match('/^\|.*\|$/su', $line) !== 1) {
                        return $m[0];
                    }
                    $rows[] = array_map('trim', explode('|', substr($line, 1, -1)));
                }

                $cols  = count($rows[0]);
                $cells = static fn (array $row, string $tag): string => '<tr>' . implode('', array_map(
                    static fn (string $c): string => "<{$tag}>{$c}</{$tag}>",
                    array_slice(array_pad($row, $cols, ''), 0, $cols),
                )) . '</tr>';

                $body = implode('', array_map(static fn (array $r): string => $cells($r, 'td'), array_slice($rows, 1)));

                return '<table><thead>' . $cells($rows[0], 'th') . '</thead>' . ($body === '' ? '' : "<tbody>{$body}</tbody>") . '</table>';
            },
            $html,
        ) ?? $html;
    }
}

if (! function_exists('post_markdown_headings')) {
    /**
     * 정화된 HTML 에서 "## 제목" 한 줄짜리 문단(<p>)을 <h2> 등 제목 태그로 바꾼다 (# 1~6개 + 공백).
     * 문단 전체가 제목 한 줄인 경우만 대상이라 pre·code 안이나 문장 중간의 #은 건드리지 않는다.
     */
    function post_markdown_headings(string $html): string
    {
        return preg_replace_callback(
            '~<p>\s*(#{1,6})\s+([^<\s][^<]*?)\s*</p>~u',
            static fn (array $m): string => '<h' . strlen($m[1]) . '>' . $m[2] . '</h' . strlen($m[1]) . '>',
            $html,
        ) ?? $html;
    }
}

if (! function_exists('post_markdown_blocks')) {
    /**
     * 정화된 HTML 에서 마크다운 블록 문법으로만 이뤄진 문단(<p>)을 바꾼다.
     * 목록(- / 1.)·인용(>)은 문단의 모든 줄(<br> 구분)이 해당될 때만, 구분선(---)은 문단 전체일 때만 대상이다.
     * 연속한 목록 문단은 하나의 <ul>/<ol> 로 합친다. pre·code 안은 <p> 가 아니라 건드리지 않는다.
     */
    function post_markdown_blocks(string $html): string
    {
        $converted = preg_replace_callback(
            '~<p>\s*(.*?)\s*</p>~su',
            static function (array $m): string {
                $lines = preg_split('~\s*<br\s*/?>\s*~i', $m[1]) ?: [$m[1]];

                if (preg_match('/^(?:-{3,}|\*{3,}|_{3,})$/', $m[1]) === 1) {
                    return '<hr />';
                }

                foreach (['ul' => '/^[-*+]\s+(\S.*)$/su', 'ol' => '/^\d{1,3}[.)]\s+(\S.*)$/su'] as $tag => $pattern) {
                    $items = post_markdown_strip_lines($lines, $pattern);
                    if ($items !== null) {
                        return "<{$tag}><li>" . implode('</li><li>', $items) . "</li></{$tag}>";
                    }
                }

                $quoted = post_markdown_strip_lines($lines, '/^(?:&gt;|>)\s?(\S.*)$/su');

                return $quoted === null ? $m[0] : '<blockquote><p>' . implode('<br />', $quoted) . '</p></blockquote>';
            },
            $html,
        );

        return (string) preg_replace('~</(ul|ol)>\s*<\1>~', '', $converted ?? $html);
    }
}

if (! function_exists('post_markdown_strip_lines')) {
    /**
     * 모든 줄이 패턴(첫 캡처 = 본문)에 맞으면 본문 배열을, 하나라도 어긋나면 null 을 돌려준다.
     *
     * @param list<string> $lines
     *
     * @return list<string>|null
     */
    function post_markdown_strip_lines(array $lines, string $pattern): ?array
    {
        $out = [];

        foreach ($lines as $line) {
            if (preg_match($pattern, trim($line), $m) !== 1) {
                return null;
            }
            $out[] = $m[1];
        }

        return $out;
    }
}

if (! function_exists('post_markdown_inline')) {
    /**
     * 정화된 HTML 의 본문 텍스트에서 마크다운 인라인 문법을 바꾼다:
     * `코드` → <code>, **굵게** → <strong>, *기울임* → <em>, [글](주소) → <a>.
     * 태그 속성과 pre·code·a 안은 건드리지 않는다. 링크는 http(s)·루트 상대 경로만 허용한다.
     */
    function post_markdown_inline(string $html): string
    {
        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }

        $skipDepth = 0;

        foreach ($parts as $i => $part) {
            if (preg_match('~^<(/?)(?:pre|code|a)[\s>]~i', $part, $m) === 1) {
                $skipDepth += $m[1] === '/' ? -1 : 1;
            } elseif ($skipDepth <= 0 && ($part === '' || $part[0] !== '<') && strpbrk($part, '`*[') !== false) {
                // PCRE 한도 초과로 null 이 오면 원문을 그대로 둔다 (본문이 지워지지 않게)
                $parts[$i] = preg_replace_callback(
                    '~`([^`]+)`|\*\*(?=\S)([^*]+?)(?<=\S)\*\*|(?<![*\w])\*(?=\S)([^*]+?)(?<=\S)\*(?![*\w])|\[([^\]]+)\]\(((?:https?://|/)[^\s)"<>]+)\)~u',
                    static fn (array $m): string => match (true) {
                        $m[1] !== null => '<code>' . $m[1] . '</code>',
                        $m[2] !== null => '<strong>' . $m[2] . '</strong>',
                        $m[3] !== null => '<em>' . $m[3] . '</em>',
                        default        => '<a href="' . $m[5] . '">' . $m[4] . '</a>',
                    },
                    $part,
                    flags: PREG_UNMATCHED_AS_NULL,
                ) ?? $part;
            }
        }

        return implode('', $parts);
    }
}
