<?php
/**
 * 블로그형 테마 공용 사이드바 내용 — 사이드바 광고 + 많이 본 글 + 게시판 카테고리.
 * 내용이 없으면 빈 문자열을 낸다(호출 측이 aside 를 그리지 않도록 trim 으로 판정).
 *
 * @var list<array<string, mixed>> $menus
 * @var array<string, list<array<string, mixed>>> $boardCategories
 * @var list<array<string, mixed>> $sidebarAds
 * @var list<array<string, mixed>> $popularPosts
 * @var string $popularPeriod
 * @var bool $withCategories
 */
$groups = [];
if (! empty($withCategories)) {
    $items = [];
    foreach ($menus as $menu) {
        $items[] = $menu;
        foreach (($menu['children'] ?? []) as $child) {
            $items[] = $child;
        }
    }
    foreach ($items as $item) {
        $url = (string) ($item['url'] ?? '');
        if (! str_starts_with($url, '/board/')) {
            continue;
        }
        $slug = trim(substr($url, 7), '/');
        if ($slug !== '' && ! empty($boardCategories[$slug]) && ! isset($groups[$slug])) {
            $groups[$slug] = ['title' => (string) $item['title'], 'categories' => $boardCategories[$slug]];
        }
    }
}
?>
<?= view('themes/default/components/ad_unit', ['ads' => $sidebarAds ?? [], 'slot' => 'sidebar']) ?>
<?php if ($groups !== []): ?>
<nav class="side-cats" aria-label="카테고리">
    <h2 class="side-cats__title">카테고리</h2>
    <?php foreach ($groups as $slug => $group): ?>
        <?php if (count($groups) > 1): ?><h3 class="side-cats__board"><?= esc($group['title']) ?></h3><?php endif; ?>
        <ul class="side-cats__list">
            <li><a href="/board/<?= esc($slug) ?>">전체</a></li>
            <?php foreach ($group['categories'] as $category): ?>
            <li><a href="/board/<?= esc($slug) ?>?category=<?= esc($category['id']) ?>"><?= esc($category['name']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
<?php if (! empty($popularPosts)): ?>
<section class="side-popular" aria-label="많이 본 글">
    <h2 class="side-cats__title">많이 본 글</h2>
    <p class="side-popular__periods">
        <?php foreach (\App\Models\PostModel::POPULAR_PERIODS as $key => $label): ?>
        <a href="?popular=<?= esc($key) ?>"<?= ($popularPeriod ?? '7d') === $key ? ' aria-current="true"' : '' ?>><?= esc($label) ?></a>
        <?php endforeach; ?>
    </p>
    <ol class="side-cats__list">
        <?php foreach ($popularPosts as $post): ?>
        <li><a href="/board/<?= esc($post['board_slug']) ?>/<?= esc($post['id']) ?>"><?= esc($post['title']) ?></a></li>
        <?php endforeach; ?>
    </ol>
</section>
<?php endif; ?>
