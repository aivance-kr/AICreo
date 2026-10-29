<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Models\PostModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * 월별 아카이브 — 연·월별 글 수 /archive, 월별 글 목록 /archive/{YYYY}/{MM}
 */
class ArchiveController extends BaseController
{
    private const PER_PAGE = 20;
    private const MIN_YEAR = 2000;

    public function index(): string
    {
        $counts = (new PostModel())->getArchiveCounts();

        return $this->render('archive/index', [
            'counts' => $counts,
            'page'   => [
                'title'     => '아카이브',
                'meta_desc' => '연·월별 글 아카이브',
                'canonical' => base_url('archive'),
                'noindex'   => $counts === [],
            ],
        ]);
    }

    public function month(string $year, string $month): string
    {
        $y = (int) $year;
        $m = (int) $month;
        if ($y < self::MIN_YEAR || $y > (int) date('Y') || $m < 1 || $m > 12) {
            throw PageNotFoundException::forPageNotFound();
        }

        $pageNo = max(1, (int) ($this->request->getGet('page') ?? 1));
        $result = (new PostModel())->getArchivePosts($y, $m, $pageNo, self::PER_PAGE);
        $label  = sprintf('%d년 %d월', $y, $m);

        return $this->render('archive/month', [
            'label'       => $label,
            'posts'       => $result['posts'],
            'total'       => $result['total'],
            'currentPage' => $pageNo,
            'totalPages'  => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
            'baseUrl'     => base_url(sprintf('archive/%04d/%02d', $y, $m)),
            'page'        => [
                'title'     => $label . ' 글',
                'meta_desc' => $label . '에 작성된 글 목록',
                'canonical' => base_url(sprintf('archive/%04d/%02d', $y, $m)),
                'noindex'   => $result['posts'] === [],
            ],
        ]);
    }
}
