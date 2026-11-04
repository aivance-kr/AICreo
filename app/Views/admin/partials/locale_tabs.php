<?php
/**
 * 언어 전환 탭 — 사용 언어가 둘 이상일 때만 보인다.
 *
 * @var list<string> $locales       사용 언어
 * @var string|null  $currentLocale 선택된 언어 (null 이면 "전체")
 * @var bool         $withAll       "전체" 탭 포함 여부
 */
$withAll = $withAll ?? true;
if (count($locales) < 2) {
    return;
}
?>
<ul class="nav nav-pills mb-3" aria-label="언어">
    <?php if ($withAll): ?>
    <li class="nav-item"><a class="nav-link py-1 <?= $currentLocale === null ? 'active' : '' ?>" href="?">전체</a></li>
    <?php endif; ?>
    <?php foreach ($locales as $l): ?>
    <li class="nav-item"><a class="nav-link py-1 <?= $currentLocale === $l ? 'active' : '' ?>" href="?locale=<?= esc($l, 'url') ?>"><?= esc(strtoupper($l)) ?></a></li>
    <?php endforeach; ?>
</ul>
