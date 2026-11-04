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
        $counts = (new PostModel())->getArchiveCounts($this->request->getLocale());

        return $this->render('archive/index', [
            'counts' => $counts,
            'page'   => [
                'title'     => lang('Content.archive'),
                'meta_desc' => lang('Content.archiveDesc'),
                'canonical' => locale_url('archive'),
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
        $result = (new PostModel())->getArchivePosts($y, $m, $pageNo, self::PER_PAGE, $this->request->getLocale());
        $label  = lang('Content.yearMonthLabel', ['year' => (string) $y, 'month' => (string) $m, 'monthName' => date('F', mktime(0, 0, 0, $m, 1))]);

        return $this->render('archive/month', [
            'label'       => $label,
            'posts'       => $result['posts'],
            'total'       => $result['total'],
            'currentPage' => $pageNo,
            'totalPages'  => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
            'baseUrl'     => locale_url(sprintf('archive/%04d/%02d', $y, $m)),
            'page'        => [
                'title'     => lang('Content.monthPostsTitle', ['label' => $label]),
                'meta_desc' => lang('Content.monthPostsDesc', ['label' => $label]),
                'canonical' => locale_url(sprintf('archive/%04d/%02d', $y, $m)),
                'noindex'   => $result['posts'] === [],
            ],
        ]);
    }
}
