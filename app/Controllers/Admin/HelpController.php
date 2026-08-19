<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkProcessor;
use League\CommonMark\MarkdownConverter;

class HelpController extends BaseController
{
    public function index(): string
    {
        $path = ROOTPATH . 'docs/manual.md';

        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        // manual.md 가 표(GFM 문법)와 목차 내부 링크(#anchor)를 쓴다 — 순수 CommonMark
        // 컨버터로는 표가 |---|---| 그대로 남고, 헤딩에 id 가 없어 목차 링크가 죽는다.
        // GFM + HeadingPermalink(아이콘 없이 id 만) 확장을 함께 구성한다.
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

        $converter = new MarkdownConverter($environment);

        $html = $converter->convert(file_get_contents($path))->getContent();

        // 문서 자체의 h1("# AICreo 운영·개발 매뉴얼")이 페이지의 실제 h1(상단바 "도움말")과
        // 중복되지 않도록 한 단계씩 낮춘다. 헤딩에 id 속성이 붙으므로(목차 링크용) 태그
        // 이름만 정규식으로 바꾼다 — preg_replace_callback 은 원본 문자열을 한 번만 훑으므로
        // 이미 h2 로 바뀐 태그가 다음 패턴에 다시 걸려 이중으로 낮춰지는 문제가 없다.
        $html = preg_replace_callback(
            '#<(/?)h([1-4])((?:\s[^>]*)?)>#',
            static fn (array $m): string => '<' . $m[1] . 'h' . ((int) $m[2] + 1) . $m[3] . '>',
            $html,
        );

        return $this->render('admin/help/index', [
            'manualHtml' => $html,
        ]);
    }
}
