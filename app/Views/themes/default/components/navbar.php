<?php
// 현재 경로 — 활성 메뉴 표시와 aria-current 판정에 함께 쓴다.
$currentPath = '/' . uri_string();

/** 메뉴 URL 이 현재 경로인지 (자식 메뉴 포함 여부는 호출부에서 판단) */
$isCurrent = static fn (?string $url): bool => $url !== null && $url !== '' && $url === $currentPath;
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm" aria-label="주 메뉴">
    <div class="container">
        <!-- 로고 -->
        <?php if (!empty($settings['site_logo'])): ?>
            <a class="navbar-brand" href="/"><img src="/<?= esc($settings['site_logo']) ?>" alt="<?= esc($settings['site_name']) ?>" style="height:40px"></a>
        <?php else: ?>
            <a class="navbar-brand fw-bold" href="/"><?= esc($settings['site_name'] ?? '') ?></a>
        <?php endif; ?>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="메뉴 열기">
            <span class="navbar-toggler-icon" aria-hidden="true"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($menus as $menu): ?>
                <?php
                    $children  = $menu['children'] ?? [];
                    $childHit  = false;
                    foreach ($children as $c) {
                        if ($isCurrent($c['url'] ?? null)) {
                            $childHit = true;
                            break;
                        }
                    }
                    $active = $isCurrent($menu['url'] ?? null) || $childHit;
                ?>
                <li class="nav-item <?= !empty($children) ? 'dropdown' : '' ?>">
                    <?php if (!empty($children)): ?>
                        <a class="nav-link dropdown-toggle <?= $active ? 'active' : '' ?>" href="#" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false" <?= $active ? 'aria-current="true"' : '' ?>>
                            <?= esc($menu['title']) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach ($children as $child): ?>
                            <li>
                                <a class="dropdown-item <?= $isCurrent($child['url'] ?? null) ? 'active' : '' ?>"
                                   href="<?= esc($child['url']) ?>" target="<?= esc($child['target']) ?>"<?= $child['target'] === '_blank' ? ' rel="noopener"' : '' ?>
                                   <?= $isCurrent($child['url'] ?? null) ? 'aria-current="page"' : '' ?>>
                                    <?= esc($child['title']) ?><?php if ($child['target'] === '_blank'): ?><span class="visually-hidden"> (새 창 열림)</span><?php endif; ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <a class="nav-link <?= $active ? 'active' : '' ?>" href="<?= esc($menu['url']) ?>"
                           target="<?= esc($menu['target']) ?>"<?= $menu['target'] === '_blank' ? ' rel="noopener"' : '' ?>
                           <?= $active ? 'aria-current="page"' : '' ?>>
                            <?= esc($menu['title']) ?><?php if ($menu['target'] === '_blank'): ?><span class="visually-hidden"> (새 창 열림)</span><?php endif; ?>
                        </a>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>

            <!-- 우측 -->
            <div class="d-flex align-items-center gap-2">
                <?php if (!empty($settings['phone'])): ?>
                    <a href="tel:<?= esc($settings['phone']) ?>" class="btn btn-outline-primary btn-sm d-inline-flex">
                        <i class="bi bi-telephone me-1" aria-hidden="true"></i><span class="visually-hidden">전화 </span><?= esc($settings['phone']) ?>
                    </a>
                <?php endif; ?>
                <?php if ($authUser['loggedIn']): ?>
                    <span class="text-muted small d-none d-lg-inline"><?= esc($authUser['nickname']) ?></span>
                    <?php if ($authUser['role'] === 'admin'): ?>
                        <a href="/admin" class="btn btn-sm btn-outline-warning">관리자</a>
                    <?php endif; ?>
                    <a href="/auth/profile" class="btn btn-sm btn-outline-secondary">내 정보</a>
                    <a href="/auth/logout" class="btn btn-sm btn-outline-secondary">로그아웃</a>
                <?php else: ?>
                    <a href="/auth/login" class="btn btn-sm btn-outline-secondary">로그인</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
