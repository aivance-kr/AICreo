<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Models\PostModel;

class SearchController extends BaseController
{
    private const PER_PAGE = 10;

    public function index(): string
    {
        $keyword = mb_substr(trim((string) $this->request->getGet('q')), 0, 100);
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $result  = $keyword === '' ? ['posts' => [], 'total' => 0] : (new PostModel())->searchPublic($keyword, $page, self::PER_PAGE);

        return $this->render('search/index', [
            'page' => [
                'title'      => $keyword === '' ? '검색' : '"' . $keyword . '" 검색 결과',
                'meta_title' => '검색',
                'meta_desc'  => '',
                'noindex'    => true,
            ],
            'keyword'     => $keyword,
            'posts'       => $result['posts'],
            'total'       => $result['total'],
            'currentPage' => $page,
            'totalPages'  => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
        ]);
    }
}
