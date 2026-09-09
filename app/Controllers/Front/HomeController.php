<?php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Models\AdModel;
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

        $isBlogTheme = ($this->viewData['settings']['active_theme'] ?? 'default') === 'blog';
        $latestPosts = [];
        if (! $isBlogTheme && ($noticeBoard = $boardModel->getBySlug('notice'))) {
            $latestPosts = $postModel
                ->where('board_id', $noticeBoard['id'])
                ->where('is_active', 1)
                ->orderBy('id', 'DESC')
                ->findAll(3);
        }

        $blogPostLimit   = min(100, max(1, (int) ($this->viewData['settings']['blog_home_post_limit'] ?? 10)));
        $latestBlogPosts = $isBlogTheme ? $postModel->getLatestPublic($blogPostLimit) : [];

        $bannerModel       = new BannerModel();
        $adModel           = new AdModel();
        $homePage          = (new PageModel())->getBySlug('home');
        $showLatestNotices = ($this->viewData['settings']['home_show_latest_notices'] ?? '1') === '1';

        return $this->render('pages/home', [
            'page' => [
                'title'          => $homePage['title'] ?? $this->viewData['settings']['site_name'] ?? '',
                'meta_title'     => ($homePage['meta_title'] ?? '') ?: ($homePage['title'] ?? $this->viewData['settings']['site_name'] ?? ''),
                'meta_desc'      => ($homePage['meta_desc'] ?? '') ?: ($this->viewData['settings']['site_desc'] ?? ''),
                'content'        => $homePage['content'] ?? '',
                'is_custom_home' => $homePage !== null,
            ],
            'latestPosts'       => $latestPosts,
            'latestBlogPosts'   => $latestBlogPosts,
            'showLatestNotices' => $showLatestNotices,
            'mainTopBanners'    => $bannerModel->getActiveByPosition('main_top'),
            'mainBotBanners'    => $bannerModel->getActiveByPosition('main_bottom'),
            'homeTopAds'        => $adModel->getActiveByPosition('home_top'),
            'homeBottomAds'     => $adModel->getActiveByPosition('home_bottom'),
        ]);
    }
}
