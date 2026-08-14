<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ─── 홈 ──────────────────────────────────────────────────────────────────────
$routes->get('/', 'Front\HomeController::index');

// ─── 크롤러 진입점 (SEO/GEO) — 반드시 catch-all '(:segment)' 보다 위 ─────────────
$routes->get('sitemap.xml', 'Front\SitemapController::index');
$routes->get('robots.txt', 'Front\RobotsController::index');
$routes->get('llms.txt', 'Front\LlmsController::index');
$routes->get('indexnow-key.txt', 'Front\IndexNowController::key');

// ─── 인증 ────────────────────────────────────────────────────────────────────
$routes->get('auth/login', 'Front\AuthController::login');
$routes->post('auth/login', 'Front\AuthController::loginProcess');
$routes->get('auth/logout', 'Front\AuthController::logout');
$routes->get('auth/register', 'Front\AuthController::register');
$routes->post('auth/register', 'Front\AuthController::registerProcess');
$routes->get('auth/profile', 'Front\AuthController::profile');
$routes->post('auth/profile', 'Front\AuthController::profileUpdate');

// ─── 소셜 로그인 ──────────────────────────────────────────────────────────────
$routes->get('auth/social/(:segment)', 'Front\SocialAuthController::redirect/$1');
$routes->get('auth/social/(:segment)/callback', 'Front\SocialAuthController::callback/$1');

// ─── 게시판 ───────────────────────────────────────────────────────────────────
$routes->get('board/(:segment)', 'Front\BoardController::index/$1');
$routes->get('board/(:segment)/write', 'Front\BoardController::write/$1');
$routes->post('board/(:segment)/write', 'Front\BoardController::store/$1');
$routes->get('board/(:segment)/(:num)', 'Front\BoardController::view/$1/$2');
$routes->get('board/(:segment)/(:num)/edit', 'Front\BoardController::edit/$1/$2');
$routes->post('board/(:segment)/(:num)/edit', 'Front\BoardController::update/$1/$2');
$routes->post('board/(:segment)/(:num)/delete', 'Front\BoardController::delete/$1/$2');
$routes->post('board/(:segment)/(:num)/verify', 'Front\BoardController::guestVerify/$1/$2');
$routes->get('board/file/(:num)/download', 'Front\BoardController::download/$1');
$routes->post('board/(:segment)/(:num)/comment', 'Front\BoardController::commentStore/$1/$2');
$routes->post('board/(:segment)/(:num)/comment/(:num)/delete', 'Front\BoardController::commentDelete/$1/$2/$3');

// ─── 에디터 이미지 업로드 ─────────────────────────────────────────────────────
$routes->post('board/image-upload', 'Front\BoardController::imageUpload');

// ─── 문의폼 ───────────────────────────────────────────────────────────────────
$routes->post('inquiry/submit', 'Front\PageController::inquirySubmit');

// ─── 관리자 ──────────────────────────────────────────────────────────────────
$routes->group('admin', ['filter' => 'auth:admin'], static function ($routes): void {
    // 대시보드
    $routes->get('', 'Admin\DashboardController::index');
    $routes->get('dashboard', 'Admin\DashboardController::index');

    // 페이지 관리
    $routes->get('pages', 'Admin\PageManagerController::index');
    $routes->get('pages/create', 'Admin\PageManagerController::create');
    $routes->post('pages/create', 'Admin\PageManagerController::store');
    $routes->get('pages/(:num)/edit', 'Admin\PageManagerController::edit/$1');
    $routes->post('pages/(:num)/edit', 'Admin\PageManagerController::update/$1');
    $routes->post('pages/(:num)/delete', 'Admin\PageManagerController::delete/$1');

    // 게시판 관리
    $routes->get('boards', 'Admin\BoardManagerController::index');
    $routes->get('boards/create', 'Admin\BoardManagerController::create');
    $routes->post('boards/create', 'Admin\BoardManagerController::store');
    $routes->get('boards/(:num)/edit', 'Admin\BoardManagerController::edit/$1');
    $routes->post('boards/(:num)/edit', 'Admin\BoardManagerController::update/$1');
    $routes->get('boards/(:num)/posts', 'Admin\BoardManagerController::posts/$1');
    $routes->post('posts/(:num)/delete', 'Admin\BoardManagerController::deletePost/$1');

    // 메뉴 관리
    $routes->get('menus', 'Admin\MenuController::index');
    $routes->post('menus', 'Admin\MenuController::store');
    $routes->post('menus/reorder', 'Admin\MenuController::reorder');
    $routes->post('menus/(:num)/edit', 'Admin\MenuController::update/$1');
    $routes->post('menus/(:num)/delete', 'Admin\MenuController::delete/$1');

    // 미디어 라이브러리
    $routes->get('media', 'Admin\MediaController::index');
    $routes->get('media/list', 'Admin\MediaController::pickerList');
    $routes->post('media/upload', 'Admin\MediaController::upload');
    $routes->post('media/(:num)/alt', 'Admin\MediaController::updateAlt/$1');
    $routes->post('media/(:num)/delete', 'Admin\MediaController::delete/$1');

    // 사이트 설정
    $routes->get('settings', 'Admin\SettingController::index');
    $routes->get('settings/(:segment)', 'Admin\SettingController::index/$1');
    $routes->post('settings/theme/upload', 'Admin\SettingController::uploadTheme');
    $routes->post('settings/(:segment)', 'Admin\SettingController::update/$1');

    // 전체 게시물 관리
    $routes->get('posts', 'Admin\PostController::index');
    $routes->post('posts/(:num)/delete', 'Admin\PostController::delete/$1');

    // 회원 관리
    $routes->get('users', 'Admin\UserController::index');
    $routes->get('users/(:num)/edit', 'Admin\UserController::edit/$1');
    $routes->post('users/(:num)/edit', 'Admin\UserController::update/$1');
    $routes->post('users/(:num)/delete', 'Admin\UserController::delete/$1');

    // 문의 수신함
    $routes->get('inquiries', 'Admin\InquiryController::index');
    $routes->get('inquiries/(:num)', 'Admin\InquiryController::view/$1');
    $routes->post('inquiries/(:num)/delete', 'Admin\InquiryController::delete/$1');

    // 배너 관리
    $routes->get('banners', 'Admin\BannerController::index');
    $routes->get('banners/create', 'Admin\BannerController::create');
    $routes->post('banners/create', 'Admin\BannerController::store');
    $routes->get('banners/(:num)/edit', 'Admin\BannerController::edit/$1');
    $routes->post('banners/(:num)/edit', 'Admin\BannerController::update/$1');
    $routes->post('banners/(:num)/delete', 'Admin\BannerController::delete/$1');

    // 팝업 관리
    $routes->get('popups', 'Admin\PopupController::index');
    $routes->get('popups/create', 'Admin\PopupController::create');
    $routes->post('popups/create', 'Admin\PopupController::store');
    $routes->get('popups/(:num)/edit', 'Admin\PopupController::edit/$1');
    $routes->post('popups/(:num)/edit', 'Admin\PopupController::update/$1');
    $routes->post('popups/(:num)/delete', 'Admin\PopupController::delete/$1');
});

// ─── 동적 페이지 (반드시 마지막에 위치) ──────────────────────────────────────────
$routes->get('(:segment)', 'Front\PageController::show/$1');
