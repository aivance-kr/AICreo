<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Models\BoardModel;
use App\Models\PostPreviewTokenModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * 비공개·예약 글 미리보기 공유 링크. 토큰이 유효하면 로그인 없이 글을 읽기 전용으로 보여 준다.
 */
class PreviewController extends BaseController
{
    public function show(string $token): ResponseInterface|string
    {
        $post  = model(PostPreviewTokenModel::class)->findValid($token) ?? throw PageNotFoundException::forPageNotFound();
        $board = model(BoardModel::class)->find((int) $post['board_id']) ?? throw PageNotFoundException::forPageNotFound();

        $html = $this->render('board/view', [
            'preview'             => true,
            'board'               => $board,
            'post'                => $post,
            'files'               => [],
            'comments'            => [],
            'likeCount'           => 0,
            'liked'               => false,
            'currentCategory'     => null,
            'listQs'              => '',
            'prevPost'            => null,
            'nextPost'            => null,
            'seriesNavs'          => [],
            'relatedPosts'        => [],
            'postTopAds'          => [],
            'postBotAds'          => [],
            'guestCommentCaptcha' => null,
            'canonicalUrl'        => base_url('board/' . $board['slug'] . '/' . $post['id']),
            'shortUrl'            => '',
            'kakaoJsKey'          => '',
            'jsonLd'              => [],
            'page'                => ['title' => $post['title'], 'noindex' => true],
        ]);

        // 토큰이 주소에 있으므로 외부 링크로 새지 않게 Referer 를 막고, 검색 색인·캐시에서 제외한다
        return $this->response
            ->setHeader('X-Robots-Tag', 'noindex, nofollow')
            ->setHeader('Referrer-Policy', 'no-referrer')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody($html);
    }
}
