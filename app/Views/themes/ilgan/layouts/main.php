<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $seo = new \App\Libraries\SeoHelper($settings);
    echo $seo->render($page ?? null);
    echo $seo->gaScript();

    // JSON-LD (GEO): 전 페이지 공통 Organization·WebSite + 페이지별 그래프
    $ld     = new \App\Libraries\Seo\JsonLdBuilder();
    $graphs = [$ld->organization($settings), $ld->website($settings)];
    foreach (($jsonLd ?? []) as $node) {
        $graphs[] = $node;
    }
    echo $ld->render($graphs);
    ?>
    <?php if (! empty($settings['favicon'])): ?>
    <link rel="icon" href="/<?= esc($settings['favicon']) ?>">
    <?php else: ?>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <?php endif; ?>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&family=Noto+Serif+KR:wght@600;900&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
          integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= asset_url('css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/post-content.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('themes/ilgan/css/style.css') ?>">
    <?= $this->renderSection('head') ?>
</head>
<body>

<a class="skip-link" href="#main-content">본문 바로가기</a>

<?php
$weekdays   = ['일', '월', '화', '수', '목', '금', '토'];
$todayLabel = date('Y년 n월 j일') . ' ' . $weekdays[(int) date('w')] . '요일';

$sidebarHtml = trim(view('themes/default/components/blog_sidebar', [
    'menus'           => $menus,
    'boardCategories' => $boardCategories ?? [],
    'sidebarAds'      => $sidebarAds ?? [],
    'withCategories'  => true,
]));
?>
<header class="il-mast">
    <div class="il-wrap">
        <div class="il-mast__top">
            <span><?= esc($todayLabel) ?></span>
            <span class="il-account">
                <?php if ($authUser['loggedIn']): ?>
                    <span><?= esc($authUser['nickname']) ?></span>
                    <?php if ($authUser['role'] === 'admin'): ?><a href="/admin">관리자</a><?php endif; ?>
                    <a href="/auth/profile">내 정보</a>
                    <a href="/auth/logout">로그아웃</a>
                <?php else: ?>
                    <a href="/auth/login">로그인</a>
                <?php endif; ?>
            </span>
        </div>
        <a class="il-brand" href="/"><?php if (! empty($settings['site_logo'])): ?>
<img src="/<?= esc($settings['site_logo']) ?>" alt="<?= esc($settings['site_name'] ?? '') ?>">
<?php else: ?><?= esc($settings['site_name'] ?? '') ?><?php endif; ?></a>
    </div>
    <nav class="navbar navbar-expand-lg il-navbar" aria-label="주 메뉴">
        <div class="il-wrap">
            <button class="navbar-toggler il-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
            aria-controls="mainNav" aria-expanded="false" aria-label="메뉴 열기">
        <span class="navbar-toggler-icon" aria-hidden="true"></span><span class="il-toggler-label">메뉴</span>
    </button>
            <div class="collapse navbar-collapse justify-content-center" id="mainNav">
                <?= view('themes/default/components/menu_items', ['menus' => $menus, 'navClass' => 'il-nav']) ?>
            </div>
        </div>
    </nav>
</header>

<div class="il-wrap">
<div class="il-shell<?= $sidebarHtml !== '' ? ' has-aside' : '' ?>">
    <main id="main-content" tabindex="-1" class="il-main">
        <?php foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $cls): ?>
            <?php if (session()->has($key)): ?>
            <div class="alert alert-<?= $cls ?> alert-dismissible fade show" role="alert">
                <?= esc(session($key)) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="알림 닫기"></button>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?= $this->renderSection('content') ?>
    </main>
    <?php if ($sidebarHtml !== ''): ?>
    <aside class="il-aside" aria-label="사이드바">
        <?= $sidebarHtml ?>
    </aside>
    <?php endif; ?>
</div>

</div>

<footer class="il-footer">
    <div class="il-wrap">
        <nav class="il-footer__nav" aria-label="하단 메뉴">
    <?php foreach ($menus as $menu): ?>
    <a href="<?= esc($menu['url']) ?>"<?= $menu['target'] === '_blank' ? ' target="_blank" rel="noopener"' : '' ?>><?= esc($menu['title']) ?><?php if ($menu['target'] === '_blank'): ?><span class="visually-hidden"> (새 창 열림)</span><?php endif; ?></a>
    <?php endforeach; ?>
</nav>
        <?= $this->include('themes/default/components/site_footer_info') ?>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer
        integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<script src="/themes/default/js/main.js" defer></script>
<?= $this->renderSection('scripts') ?>
<?= $this->include('themes/default/components/popups') ?>
<script src="/themes/default/js/popup.js" defer></script>
</body>
</html>
