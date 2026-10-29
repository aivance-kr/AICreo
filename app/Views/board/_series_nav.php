<?php $seriesPostUrl = static fn (array $p): string => '/board/' . esc((string) $p['board_slug'], 'attr') . '/' . (int) $p['id']; ?>
<details class="series-nav mt-3">
    <summary>
        <span class="series-nav__label">시리즈</span>
        <strong class="series-nav__title"><?= esc($seriesNav['series']['title']) ?></strong>
        <span class="series-nav__pos"><span class="visually-hidden">현재 위치 </span><?= (int) $seriesNav['current'] ?> / <?= (int) $seriesNav['total'] ?></span>
    </summary>
    <ol class="series-nav__list">
        <?php foreach ($seriesNav['posts'] as $i => $p): ?>
        <li<?= $i + 1 === $seriesNav['current'] ? ' aria-current="page"' : '' ?>>
            <a href="<?= $seriesPostUrl($p) ?>"><?= esc($p['title']) ?></a>
        </li>
        <?php endforeach; ?>
    </ol>
    <p class="mt-2 mb-0"><a href="/series/<?= esc($seriesNav['series']['slug'], 'attr') ?>">시리즈 소개 보기</a></p>
    <?php if ($seriesNav['prev'] || $seriesNav['next']): ?>
    <nav class="series-nav__pager" aria-label="시리즈 이전·다음 편">
        <?php if ($seriesNav['prev']): ?><a href="<?= $seriesPostUrl($seriesNav['prev']) ?>" rel="prev">← 이전 편</a><?php endif; ?>
        <?php if ($seriesNav['next']): ?><a href="<?= $seriesPostUrl($seriesNav['next']) ?>" rel="next" class="ms-auto">다음 편 →</a><?php endif; ?>
    </nav>
    <?php endif; ?>
</details>
