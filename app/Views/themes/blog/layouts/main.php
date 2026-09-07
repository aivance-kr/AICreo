<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $seo = new \App\Libraries\SeoHelper($settings);
    echo $seo->render($page ?? null);
    echo $seo->gaScript();

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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
          integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="stylesheet" href="/css/tokens.css">
    <link rel="stylesheet" href="/themes/blog/css/style.css">
    <?= $this->renderSection('head') ?>
</head>
<body>

<a class="skip-link" href="#main-content">본문 바로가기</a>

<?php
$currentPath = '/' . uri_string();
$isCurrent   = static fn (?string $url): bool => $url !== null && $url !== '' && $url === $currentPath;
?>

<?= $this->include('components/navbar') ?>

<div class="blog-content-shell">
    <aside class="blog-sidebar" aria-label="주 메뉴">
        <a class="blog-brand" href="/">
            <?php if (! empty($settings['site_logo'])): ?>
                <img src="/<?= esc($settings['site_logo']) ?>" alt="<?= esc($settings['site_name'] ?? '') ?>">
            <?php else: ?>
                <?= esc($settings['site_name'] ?? '') ?>
            <?php endif; ?>
        </a>

        <nav class="blog-navigation" aria-label="사이트 메뉴">
            <ul>
                <?php foreach ($menus as $menu): ?>
                    <?php
                    $children = $menu['children'] ?? [];
                    $childHit = false;
                    foreach ($children as $child) {
                        if ($isCurrent($child['url'] ?? null)) {
                            $childHit = true;
                            break;
                        }
                    }
                    $active = $isCurrent($menu['url'] ?? null) || $childHit;
                    ?>
                    <li>
                        <?php if (! empty($children)): ?>
                            <span class="blog-menu-label<?= $active ? ' is-active' : '' ?>"><?= esc($menu['title']) ?></span>
                            <ul class="blog-submenu">
                                <?php foreach ($children as $child): ?>
                                    <li>
                                        <a class="<?= $isCurrent($child['url'] ?? null) ? 'is-active' : '' ?>"
                                           href="<?= esc($child['url']) ?>" target="<?= esc($child['target']) ?>"<?= $child['target'] === '_blank' ? ' rel="noopener"' : '' ?>
                                           <?= $isCurrent($child['url'] ?? null) ? 'aria-current="page"' : '' ?>>
                                            <?= esc($child['title']) ?><?php if ($child['target'] === '_blank'): ?><span class="visually-hidden"> (새 창 열림)</span><?php endif; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <a class="<?= $active ? 'is-active' : '' ?>" href="<?= esc($menu['url']) ?>"
                               target="<?= esc($menu['target']) ?>"<?= $menu['target'] === '_blank' ? ' rel="noopener"' : '' ?>
                               <?= $active ? 'aria-current="page"' : '' ?>>
                                <?= esc($menu['title']) ?><?php if ($menu['target'] === '_blank'): ?><span class="visually-hidden"> (새 창 열림)</span><?php endif; ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="blog-sidebar-footer">
            <?php if ($authUser['loggedIn']): ?>
                <span><?= esc($authUser['nickname']) ?></span>
                <?php if ($authUser['role'] === 'admin'): ?><a href="/admin">관리자</a><?php endif; ?>
                <a href="/auth/profile">내 정보</a>
                <a href="/auth/logout">로그아웃</a>
            <?php else: ?>
                <a href="/auth/login">로그인</a>
            <?php endif; ?>
        </div>
    </aside>

    <main id="main-content" tabindex="-1" class="blog-main">
        <?php foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $cls): ?>
            <?php if (session()->has($key)): ?>
            <div class="container mt-3">
                <div class="alert alert-<?= $cls ?> alert-dismissible fade show" role="alert">
                    <?= esc(session($key)) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="알림 닫기"></button>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?= $this->renderSection('content') ?>
    </main>
</div>

<?= $this->include('components/footer') ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer
        integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<?= $this->renderSection('scripts') ?>
<?= $this->include('themes/default/components/popups') ?>
<script src="/themes/default/js/popup.js" defer></script>
</body>
</html>
