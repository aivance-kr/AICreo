<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\WordpressImport\WordpressImporter;
use App\Libraries\WordpressImport\WxrParser;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * 워드프레스 WXR(export XML) → AiCreo 이관.
 *
 * wp_*_id 매칭 기반 upsert라 재실행해도 안전 — 실패 후 다시 돌리면
 * 이미 이관된 항목은 갱신, 나머지는 이어서 처리한다.
 *
 * 게시판/카테고리 구조는 둘 중 하나로 뽑는다 — 관리자가 미리 게시판을 만들어둘
 * 필요 없음. --dry-run 으로 사용 가능한 메뉴 목록을 먼저 확인할 것.
 * - --menu: 워드프레스 메뉴(외모 > 메뉴)가 있는 사이트용.
 * - --category-hierarchy: 메뉴가 아예 없는 사이트용 — 카테고리 상위/하위 계층으로 대신한다.
 */
class WpImportCommand extends BaseCommand
{
    protected $group       = 'Wordpress';
    protected $name        = 'wp:import';
    protected $description = '워드프레스 WXR export XML 파일을 AiCreo 게시판/페이지/미디어로 이관합니다.';
    protected $usage       = 'wp:import <path-to-export.xml> (--menu <메뉴 slug> | --category-hierarchy) --uploads-dir <wp-content/uploads 경로> [--dry-run]';
    protected $arguments   = [
        'path' => 'WXR(.xml) 파일 경로',
    ];
    protected $options = [
        '--menu'               => '게시판 구조로 이관할 워드프레스 메뉴의 slug(nicename) — 메뉴 최상위 항목이 게시판, 그 하위 항목(카테고리 대상만)이 게시판 카테고리가 된다. --dry-run 으로 목록 확인 가능',
        '--category-hierarchy' => '메뉴가 없는 사이트용 대안 — 최상위 카테고리(부모 없음)가 게시판, 하위 카테고리가 게시판 카테고리가 된다',
        '--uploads-dir'        => '워드프레스 wp-content/uploads 절대경로 (dry-run 아니면 필수, 같은 서버에서 파일 복사)',
        '--dry-run'            => '파싱 결과 카운트만 출력, DB/파일 변경 없음',
    ];

    public function run(array $params)
    {
        $path = $params[0] ?? CLI::getSegment(1);
        if (! $path) {
            CLI::error('사용법: php spark ' . $this->usage);

            return;
        }

        $parser = new WxrParser($path);

        if (CLI::getOption('dry-run')) {
            $this->preview($parser);

            return;
        }

        $uploadsDir = CLI::getOption('uploads-dir');
        if (! $uploadsDir || ! is_dir($uploadsDir)) {
            CLI::error('--uploads-dir 로 워드프레스 wp-content/uploads 절대경로를 지정해야 합니다.');

            return;
        }

        $menuNicename = CLI::getOption('menu');
        $useHierarchy = (bool) CLI::getOption('category-hierarchy');

        if (! $menuNicename && ! $useHierarchy) {
            CLI::error('--menu 로 이관할 워드프레스 메뉴의 slug를 지정하거나, 메뉴가 없는 사이트라면 --category-hierarchy 를 사용해야 합니다 (--dry-run 으로 확인 가능).');

            return;
        }

        $importer = new WordpressImporter();

        if ($useHierarchy) {
            CLI::write('카테고리 상위/하위 계층으로 게시판/카테고리를 생성합니다.', 'yellow');
            $routing = $importer->importCategoryHierarchyAsBoards($parser);
        } else {
            CLI::write("메뉴 \"{$menuNicename}\" 구조로 게시판/카테고리를 생성합니다.", 'yellow');
            $routing = $importer->importMenuAsBoards($parser, (string) $menuNicename);
        }
        CLI::write('  게시판/카테고리 매핑 ' . count($routing) . '건');

        CLI::write('첨부파일 복사 중...', 'yellow');
        $attachmentResult = $importer->importAttachments($parser, $uploadsDir);
        CLI::write("  복사 {$attachmentResult['copied']}건, 실패 {$attachmentResult['failed']}건");

        CLI::write('글·페이지 이관 중...', 'yellow');
        $contentResult = $importer->importPagesAndPosts($parser, $routing);
        CLI::write("  페이지 {$contentResult['pages']}건, 글 {$contentResult['posts']}건, 건너뜀 {$contentResult['skipped']}건");

        CLI::write('본문 이미지 경로 치환 중...', 'yellow');
        $rewritten = $importer->rewriteContentImages($attachmentResult['pathMap']);
        CLI::write("  치환된 글/페이지 {$rewritten}건");

        CLI::write('댓글 이관 중...', 'yellow');
        $commentCount = $importer->importComments($parser);
        CLI::write("  댓글 {$commentCount}건");

        CLI::write('리다이렉트 매핑 생성 중...', 'yellow');
        $redirectCount = $importer->buildRedirects($parser, $parser->siteLink());
        CLI::write("  리다이렉트 {$redirectCount}건");

        $warnings = $importer->warnings();
        if ($warnings !== []) {
            CLI::newLine();
            CLI::write('경고 ' . count($warnings) . '건:', 'red');

            foreach ($warnings as $warning) {
                CLI::write('  - ' . $warning, 'red');
            }
        }

        CLI::newLine();
        CLI::write('이관 완료.', 'green');
    }

    private function preview(WxrParser $parser): void
    {
        CLI::write('=== 파싱 미리보기 (DB 변경 없음) ===', 'yellow');
        CLI::write('사이트: ' . $parser->siteLink());

        $menus    = $parser->navMenus();
        $menuList = $menus === []
            ? '없음'
            : implode(', ', array_map(static fn (array $m): string => "{$m['name']}({$m['nicename']})", $menus));
        CLI::write('메뉴: ' . $menuList);

        $categories = $parser->categories();
        $rootCount  = count(array_filter($categories, static fn ($c) => $c->isRoot()));
        CLI::write('카테고리: ' . count($categories) . "건 (최상위 {$rootCount}건 — --category-hierarchy 사용 시 게시판 수)");
        CLI::write('페이지: ' . count($parser->pages()) . '건');
        CLI::write('글: ' . count($parser->posts()) . '건');
        CLI::write('첨부파일: ' . count($parser->attachments()) . '건');
        CLI::write('댓글: ' . count($parser->comments()) . '건');
    }
}
