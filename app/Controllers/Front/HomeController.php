<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Models\BannerModel;
use App\Models\BoardModel;
use App\Models\PageModel;
use App\Models\PostModel;

class HomeController extends BaseController
{
    public function index(): string
    {
        // 홈에 최신 공지 3개 노출
        $boardModel = new BoardModel();
        $postModel  = new PostModel();

        $noticeBoard = $boardModel->getBySlug('notice');
        $latestPosts = [];
        if ($noticeBoard) {
            $latestPosts = $postModel
                ->where('board_id', $noticeBoard['id'])
                ->orderBy('id', 'DESC')
                ->findAll(3);
        }

        $bannerModel = new BannerModel();
        $homePage    = (new PageModel())->getBySlug('home');

        return $this->render('pages/home', [
            'page' => [
                'title'      => $homePage['title'] ?? $this->viewData['settings']['site_name'] ?? '',
                'meta_title' => ($homePage['meta_title'] ?? '') ?: ($homePage['title'] ?? $this->viewData['settings']['site_name'] ?? ''),
                'meta_desc'  => ($homePage['meta_desc'] ?? '') ?: ($this->viewData['settings']['site_desc'] ?? ''),
                'content'    => $homePage['content'] ?? '',
            ],
            'latestPosts'    => $latestPosts,
            'mainTopBanners' => $bannerModel->getActiveByPosition('main_top'),
            'mainBotBanners' => $bannerModel->getActiveByPosition('main_bottom'),
        ]);
    }
}
