<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Models\PostModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * 태그 목록 /tags, 태그별 글 목록 /tags/{tag} — 공개 글 기준.
 */
class TagController extends BaseController
{
    private const PER_PAGE = 10;

    public function index(): string
    {
        $tags = (new PostModel())->getPublicTagCounts($this->request->getLocale());

        return $this->render('tags/index', [
            'tags' => $tags,
            'page' => [
                'title'     => lang('Content.tags'),
                'meta_desc' => lang('Content.tagsDesc'),
                'canonical' => locale_url('tags'),
                'noindex'   => $tags === [],
            ],
        ]);
    }

    public function show(string $tag): string
    {
        $tag    = urldecode($tag);
        $page   = max(1, (int) ($this->request->getGet('page') ?? 1));
        $result = (new PostModel())->searchPublicByTag($tag, $page, self::PER_PAGE, $this->request->getLocale());
        if ($result['total'] === 0) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->render('tags/show', [
            'tag'         => $tag,
            'posts'       => $result['posts'],
            'total'       => $result['total'],
            'currentPage' => $page,
            'totalPages'  => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
            'page'        => [
                'title'     => '#' . $tag,
                'meta_desc' => lang('Content.tagMetaDesc', ['tag' => $tag, 'count' => (string) $result['total']]),
                'canonical' => locale_url('tags/' . rawurlencode($tag)),
                'noindex'   => $page > 1,
            ],
        ]);
    }
}
