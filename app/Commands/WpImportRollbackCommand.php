<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\BoardCategoryModel;
use App\Models\MediaModel;
use App\Models\PageModel;
use App\Models\PostCommentModel;
use App\Models\PostModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * wp:import 로 이관된 데이터만 삭제 (wp_*_id 가 NOT NULL 인 행).
 * 관리자가 직접 만든 네이티브 콘텐츠는 이 컬럼이 항상 NULL이라 영향 없음.
 * 대상 게시판(boards)은 이관 전에 관리자가 미리 만들어둔 것이라 삭제 대상이
 * 아니고, 그 아래 board_categories 중 wp_term_id가 채워진 행만 삭제한다.
 * 리다이렉트(redirects)는 SEO 보존 목적이라 롤백 대상에서 제외 — 지울 땐 수동으로.
 */
class WpImportRollbackCommand extends BaseCommand
{
    protected $group       = 'Wordpress';
    protected $name        = 'wp:import:rollback';
    protected $description = 'wp:import 로 이관된 데이터를 전부 삭제합니다.';
    protected $usage       = 'wp:import:rollback [--force]';
    protected $options     = [
        '--force' => '확인 없이 바로 삭제',
    ];

    public function run(array $params)
    {
        if (! CLI::getOption('force')) {
            $confirmed = CLI::prompt('이관된 카테고리·글·페이지·댓글·미디어를 전부 삭제합니다(대상 게시판 자체는 유지). 계속할까요?', ['y', 'n']);
            if ($confirmed !== 'y') {
                CLI::write('취소했습니다.', 'yellow');

                return;
            }
        }

        $commentModel = new PostCommentModel();
        $commentModel->where('wp_comment_id IS NOT NULL')->delete(null, true);
        CLI::write('댓글 삭제: ' . $commentModel->db->affectedRows() . '건');

        $mediaModel = new MediaModel();

        foreach ($mediaModel->where('wp_attachment_id IS NOT NULL')->findAll() as $media) {
            $mediaModel->deleteWithFile($media['id']);
        }
        CLI::write('미디어 삭제 완료');

        $postModel = new PostModel();
        $postModel->where('wp_post_id IS NOT NULL')->delete(null, true);
        CLI::write('글 삭제: ' . $postModel->db->affectedRows() . '건');

        $pageModel = new PageModel();
        $pageModel->where('wp_post_id IS NOT NULL')->delete();
        CLI::write('페이지 삭제: ' . $pageModel->db->affectedRows() . '건');

        $categoryModel = new BoardCategoryModel();
        $categoryModel->where('wp_term_id IS NOT NULL')->delete();
        CLI::write('카테고리 삭제: ' . $categoryModel->db->affectedRows() . '건');

        CLI::write('롤백 완료. 대상 게시판과 redirects 테이블(SEO 보존 목적)은 그대로 둡니다.', 'green');
    }
}
