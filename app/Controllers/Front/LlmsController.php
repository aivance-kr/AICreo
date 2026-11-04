<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Libraries\Seo\HomeIntro;
use App\Models\BoardModel;
use App\Models\PageModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\I18n;

/**
 * 동적 llms.txt (GEO) — AI 크롤러용 사이트 요약(markdown).
 * 사이트 개요·주요 페이지·공개 게시판·연락처. 전 링크 base_url() 절대경로.
 * 결과는 seo_llms 로 1시간 캐시(페이지/게시판 변경 시 무효화).
 * 참고: https://llmstxt.org
 */
class LlmsController extends BaseController
{
    private const CACHE_KEY = 'seo_llms';
    private const CACHE_TTL = 3600;

    public function index(): ResponseInterface
    {
        $body = cache()->remember(self::CACHE_KEY, self::CACHE_TTL, fn (): string => $this->build());

        return $this->response
            ->setContentType('text/plain')
            ->setBody($body);
    }

    private function build(): string
    {
        $settings = (new SettingModel())->getAllAsMap();

        $lines = [];

        // 제목 + 요약 (llmstxt.org: H1 + blockquote)
        $lines[] = '# ' . ($settings['site_name'] ?? '');
        if (! empty($settings['site_desc'])) {
            $lines[] = '';
            $lines[] = '> ' . $settings['site_desc'];
        }

        // 기본 소개 홈일 때만 정답형 FAQ 를 싣는다(관리자가 home 을 저장하면 그 내용이 우선).
        if ((new PageModel())->getBySlug('home', config(I18n::class)->defaultLocale) === null) {
            $lines[] = '';
            $lines[] = '## 자주 묻는 질문';

            foreach (HomeIntro::faqs() as $faq) {
                $lines[] = '- ' . $faq['q'] . ' ' . $faq['a'];
            }
        }

        // 주요 페이지 (발행)
        $pages = (new PageModel())->getPublished(config(I18n::class)->defaultLocale);
        if ($pages !== []) {
            $lines[] = '';
            $lines[] = '## 주요 페이지';

            foreach ($pages as $page) {
                $desc    = $page['meta_desc'] ?? '';
                $entry   = '- [' . $page['title'] . '](' . base_url($page['slug']) . ')';
                $lines[] = $desc !== '' ? $entry . ': ' . $desc : $entry;
            }
        }

        // 공개 게시판 (활성 · guest 열람)
        $boards = array_filter(
            (new BoardModel())->getActiveBoards(),
            static fn (array $b): bool => $b['read_permission'] === 'guest',
        );
        if ($boards !== []) {
            $lines[] = '';
            $lines[] = '## 게시판';

            foreach ($boards as $board) {
                $desc    = $board['description'] ?? '';
                $entry   = '- [' . $board['name'] . '](' . base_url('board/' . $board['slug']) . ')';
                $lines[] = $desc !== '' ? $entry . ': ' . $desc : $entry;
            }
        }

        // 연락처
        $contact = [];
        if (! empty($settings['phone'])) {
            $contact[] = '- 전화: ' . $settings['phone'];
        }
        if (! empty($settings['email'])) {
            $contact[] = '- 이메일: ' . $settings['email'];
        }
        if (! empty($settings['address'])) {
            $contact[] = '- 주소: ' . $settings['address'];
        }
        if ($contact !== []) {
            $lines[] = '';
            $lines[] = '## 연락처';

            foreach ($contact as $c) {
                $lines[] = $c;
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
