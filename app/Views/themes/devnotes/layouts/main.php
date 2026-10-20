<!DOCTYPE html>
<html lang="ko" data-bs-theme="dark">
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Noto+Sans+KR:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
          integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= asset_url('css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('css/post-content.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('themes/devnotes/css/style.css') ?>">
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
    'withCategories'  => false,
]));
?>
<div class="dn-frame">
    <header class="dn-rail">
        <div class="dn-rail__head">
            <a class="dn-brand" href="/"><?php if (! empty($settings['site_logo'])): ?>
<img src="/<?= esc($settings['site_logo']) ?>" alt="<?= esc($settings['site_name'] ?? '') ?>">
<?php else: ?><?= esc($settings['site_name'] ?? '') ?><?php endif; ?></a>
            <button class="navbar-toggler dn-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="메뉴 열기">
                <span class="navbar-toggler-icon" aria-hidden="true"></span>
            </button>
        </div>
        <?php if (! empty($settings['site_desc'])): ?><p class="dn-rail__desc"><?= esc($settings['site_desc']) ?></p><?php endif; ?>
        <div class="collapse dn-rail__collapse" id="mainNav">
            <nav class="dn-menu" aria-label="주 메뉴">
                <?php
                $currentPath = '/' . uri_string();
                $isCurrent   = static fn (?string $url): bool => $url !== null && $url !== '' && $url === $currentPath;
                ?>
                <ul>
                    <?php foreach ($menus as $menu): ?>
                    <?php
                    $children = $menu['children'] ?? [];
                    $active   = $isCurrent($menu['url'] ?? null);
                    foreach ($children as $child) {
                        $active = $active || $isCurrent($child['url'] ?? null);
                    }
                    $boardSlug  = str_starts_with((string) ($menu['url'] ?? ''), '/board/') ? trim((string) substr((string) $menu['url'], 7), '/') : '';
                    $categories = $boardSlug !== '' ? ($boardCategories[$boardSlug] ?? []) : [];
                    ?>
                    <li>
                        <?php if ($children !== []): ?>
                        <span class="dn-menu__label<?= $active ? ' is-active' : '' ?>"><?= esc($menu['title']) ?></span>
                        <ul class="dn-submenu">
                            <?php foreach ($children as $child): ?>
                            <li><a class="<?= $isCurrent($child['url'] ?? null) ? 'is-active' : '' ?>" href="<?= esc($child['url']) ?>"
                                   target="<?= esc($child['target']) ?>"<?= $child['target'] === '_blank' ? ' rel="noopener"' : '' ?>
                                   <?= $isCurrent($child['url'] ?? null) ? 'aria-current="page"' : '' ?>><?= esc($child['title']) ?><?php if ($child['target'] === '_blank'): ?><span class="visually-hidden"> (새 창 열림)</span><?php endif; ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <a class="<?= $active ? 'is-active' : '' ?>" href="<?= esc($menu['url']) ?>"
                           target="<?= esc($menu['target']) ?>"<?= $menu['target'] === '_blank' ? ' rel="noopener"' : '' ?>
                           <?= $active ? 'aria-current="page"' : '' ?>><?= esc($menu['title']) ?><?php if ($menu['target'] === '_blank'): ?><span class="visually-hidden"> (새 창 열림)</span><?php endif; ?></a>
                        <?php if ($categories !== []): ?>
                        <ul class="dn-submenu">
                            <li><a href="/board/<?= esc($boardSlug) ?>">전체</a></li>
                            <?php foreach ($categories as $category): ?>
                            <li><a href="/board/<?= esc($boardSlug) ?>?category=<?= esc($category['id']) ?>"><?= esc($category['name']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <div class="dn-account">
                <?php if ($authUser['loggedIn']): ?>
                    <span><?= esc($authUser['nickname']) ?></span>
                    <?php if ($authUser['role'] === 'admin'): ?><a href="/admin">관리자</a><?php endif; ?>
                    <a href="/auth/profile">내 정보</a>
                    <a href="/auth/logout">로그아웃</a>
                <?php else: ?>
                    <a href="/auth/login">로그인</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="dn-body">
<div class="dn-shell<?= $sidebarHtml !== '' ? ' has-aside' : '' ?>">
    <main id="main-content" tabindex="-1" class="dn-main">
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
    <aside class="dn-aside" aria-label="사이드바">
        <?= $sidebarHtml ?>
    </aside>
    <?php endif; ?>
</div>

        <footer class="dn-footer">
            <nav class="dn-footer__nav" aria-label="하단 메뉴">
    <?php foreach ($menus as $menu): ?>
    <a href="<?= esc($menu['url']) ?>"<?= $menu['target'] === '_blank' ? ' target="_blank" rel="noopener"' : '' ?>><?= esc($menu['title']) ?><?php if ($menu['target'] === '_blank'): ?><span class="visually-hidden"> (새 창 열림)</span><?php endif; ?></a>
    <?php endforeach; ?>
</nav>
            <?= $this->include('themes/default/components/site_footer_info') ?>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer
        integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<script src="/themes/default/js/main.js" defer></script>
<?= $this->renderSection('scripts') ?>
<?= $this->include('themes/default/components/popups') ?>
<script src="/themes/default/js/popup.js" defer></script>
</body>
</html>
