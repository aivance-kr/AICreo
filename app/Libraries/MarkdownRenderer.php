<?php

declare(strict_types=1);

namespace App\Libraries;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkProcessor;
use League\CommonMark\MarkdownConverter;

/**
 * 관리자 문서(매뉴얼·플러그인 설명)용 마크다운 → HTML 변환.
 *
 * GFM(표) + 헤딩 id(목차 #anchor 링크)를 쓰고, 원문 HTML·위험한 링크는 막는다.
 * 문서 자체의 h1 이 화면의 h1 과 겹치지 않도록 헤딩을 한 단계씩 낮춘다.
 */
final class MarkdownRenderer
{
    public static function toHtml(string $markdown): string
    {
        $environment = new Environment([
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink'  => [
                'insert'              => HeadingPermalinkProcessor::INSERT_NONE,
                'apply_id_to_heading' => true,
                'id_prefix'           => '',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new HeadingPermalinkExtension());

        $html = (new MarkdownConverter($environment))->convert($markdown)->getContent();

        // 헤딩에 id 속성이 붙으므로 태그 이름만 정규식으로 바꾼다. preg_replace_callback 은
        // 원본을 한 번만 훑어 이미 낮춘 태그가 다시 걸리지 않는다.
        return (string) preg_replace_callback(
            '#<(/?)h([1-4])((?:\s[^>]*)?)>#',
            static fn (array $m): string => '<' . $m[1] . 'h' . ((int) $m[2] + 1) . $m[3] . '>',
            $html,
        );
    }
}
