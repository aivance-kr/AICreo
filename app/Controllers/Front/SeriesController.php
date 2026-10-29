<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Models\SeriesModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * 시리즈(연재) 소개 페이지 — /series/{slug}
 */
class SeriesController extends BaseController
{
    public function show(string $slug): string
    {
        $model  = new SeriesModel();
        $series = $model->findBySlug($slug);
        if ($series === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $posts = $model->publicPosts((int) $series['id']);

        return $this->render('series/show', [
            'series' => $series,
            'posts'  => $posts,
            'page'   => [
                'title'     => $series['title'],
                'meta_desc' => mb_substr(trim(strip_tags((string) $series['description'])), 0, 150),
                'canonical' => base_url('series/' . $series['slug']),
                'noindex'   => $posts === [],
            ],
        ]);
    }
}
