<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\WordpressImport\WordpressImporter;
use App\Libraries\WordpressImport\WxrParser;
use App\Models\BoardModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * 워드프레스 WXR(export XML) → AiCreo 이관.
 *
 * wp_*_id 매칭 기반 upsert라 재실행해도 안전 — 실패 후 다시 돌리면
 * 이미 이관된 항목은 갱신, 나머지는 이어서 처리한다.
 */
class WpImportCommand extends BaseCommand
{
    protected $group       = 'Wordpress';
    protected $name        = 'wp:import';
    protected $description = '워드프레스 WXR export XML 파일을 AiCreo 게시판/페이지/미디어로 이관합니다.';
    protected $usage       = 'wp:import <path-to-export.xml> --board-id <게시판 ID> --uploads-dir <wp-content/uploads 경로> [--dry-run]';
    protected $arguments   = [
        'path' => 'WXR(.xml) 파일 경로',
    ];
    protected $options = [
        '--board-id'    => '글·카테고리를 이관할 대상 게시판 ID — /admin/boards 에서 미리 생성해둘 것 (dry-run 아니면 필수)',
        '--uploads-dir' => '워드프레스 wp-content/uploads 절대경로 (dry-run 아니면 필수, 같은 서버에서 파일 복사)',
        '--dry-run'     => '파싱 결과 카운트만 출력, DB/파일 변경 없음',
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

        $boardId = (int) CLI::getOption('board-id');
        $board   = $boardId > 0 ? (new BoardModel())->find($boardId) : null;
        if (! $board) {
            CLI::error('--board-id 로 글·카테고리를 이관할 게시판 ID를 지정해야 합니다 (관리자 화면 /admin/boards 에서 미리 생성).');

            return;
        }

        $importer = new WordpressImporter();

        CLI::write("게시판 \"{$board['name']}\"(id={$boardId})로 이관합니다.", 'yellow');

        CLI::write('카테고리 이관 중...', 'yellow');
        $categoryMap = $importer->importCategories($parser, $boardId);
        CLI::write('  카테고리 ' . count($categoryMap) . '건');

        CLI::write('첨부파일 복사 중...', 'yellow');
        $attachmentResult = $importer->importAttachments($parser, $uploadsDir);
        CLI::write("  복사 {$attachmentResult['copied']}건, 실패 {$attachmentResult['failed']}건");

        CLI::write('글·페이지 이관 중...', 'yellow');
        $contentResult = $importer->importPagesAndPosts($parser, $boardId, $categoryMap);
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
        CLI::write('카테고리: ' . count($parser->categories()) . '건');
        CLI::write('페이지: ' . count($parser->pages()) . '건');
        CLI::write('글: ' . count($parser->posts()) . '건');
        CLI::write('첨부파일: ' . count($parser->attachments()) . '건');
        CLI::write('댓글: ' . count($parser->comments()) . '건');
    }
}
