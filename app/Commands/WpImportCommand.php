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
 */
class WpImportCommand extends BaseCommand
{
    protected $group       = 'Wordpress';
    protected $name        = 'wp:import';
    protected $description = '워드프레스 WXR export XML 파일을 AiCreo 게시판/페이지/미디어로 이관합니다.';
    protected $usage       = 'wp:import <path-to-export.xml> [--dry-run]';
    protected $arguments   = [
        'path' => 'WXR(.xml) 파일 경로',
    ];
    protected $options = [
        '--dry-run' => '파싱 결과 카운트만 출력, DB/파일 변경 없음',
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

        $importer = new WordpressImporter();

        CLI::write('카테고리 → 게시판 이관 중...', 'yellow');
        $boardMap = $importer->importCategories($parser);
        CLI::write('  게시판 ' . count($boardMap) . '건');

        CLI::write('첨부파일 다운로드 중... (원본 사이트 접근 필요, 시간 걸릴 수 있음)', 'yellow');
        $attachmentResult = $importer->importAttachments($parser);
        CLI::write("  다운로드 {$attachmentResult['downloaded']}건, 실패 {$attachmentResult['failed']}건");

        CLI::write('글·페이지 이관 중...', 'yellow');
        $contentResult = $importer->importPagesAndPosts($parser, $boardMap);
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
