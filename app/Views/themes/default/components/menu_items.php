<?php
/**
 * 블로그형 테마가 공유하는 상단 메뉴 목록(2단계, 드롭다운).
 *
 * @var list<array<string, mixed>> $menus
 * @var string $navClass  <ul> 에 붙일 클래스
 */
$currentPath = '/' . uri_string();
$isCurrent   = static fn (?string $url): bool => $url !== null && $url !== '' && $url === $currentPath;
?>
<ul class="navbar-nav <?= esc($navClass ?? '', 'attr') ?>">
    <?php foreach ($menus as $menu): ?>
    <?php
        $children = $menu['children'] ?? [];
        $childHit = false;
        foreach ($children as $c) {
            if ($isCurrent($c['url'] ?? null)) {
                $childHit = true;
                break;
            }
        }
        $active = $isCurrent($menu['url'] ?? null) || $childHit;
    ?>
    <li class="nav-item<?= $children !== [] ? ' dropdown' : '' ?>">
        <?php if ($children !== []): ?>
            <a class="nav-link dropdown-toggle<?= $active ? ' active' : '' ?>" href="#" role="button"
               data-bs-toggle="dropdown" aria-expanded="false"<?= $active ? ' aria-current="true"' : '' ?>><?= esc($menu['title']) ?></a>
            <ul class="dropdown-menu">
                <?php foreach ($children as $child): ?>
                <li>
                    <a class="dropdown-item<?= $isCurrent($child['url'] ?? null) ? ' active' : '' ?>"
                       href="<?= esc($child['url']) ?>" target="<?= esc($child['target']) ?>"<?= $child['target'] === '_blank' ? ' rel="noopener"' : '' ?>
                       <?= $isCurrent($child['url'] ?? null) ? 'aria-current="page"' : '' ?>>
                        <?= esc($child['title']) ?><?php if ($child['target'] === '_blank'): ?><span class="visually-hidden"> (새 창 열림)</span><?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= esc($menu['url']) ?>"
               target="<?= esc($menu['target']) ?>"<?= $menu['target'] === '_blank' ? ' rel="noopener"' : '' ?>
               <?= $active ? 'aria-current="page"' : '' ?>>
                <?= esc($menu['title']) ?><?php if ($menu['target'] === '_blank'): ?><span class="visually-hidden"> (새 창 열림)</span><?php endif; ?>
            </a>
        <?php endif; ?>
    </li>
    <?php endforeach; ?>
</ul>
