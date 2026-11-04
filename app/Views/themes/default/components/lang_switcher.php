<?php
/**
 * 언어 전환기 — 사용 언어가 둘 이상이고 설정(show_lang_switcher)이 켜져 있을 때만 보인다.
 * 번역이 있는 화면은 그 번역 주소로, 번역 대상인데 번역이 없으면 그 언어 홈으로, 그 밖의 화면은 같은 경로로 보낸다.
 *
 * @var array<string, mixed>|null $page
 */
$switchLocales = config(\Config\I18n::class)->locales();
if (count($switchLocales) < 2 || ($settings['show_lang_switcher'] ?? '1') !== '1') {
    return;
}
$currentLocale = html_lang();
?>
<nav class="lang-switcher" aria-label="<?= esc(lang('Site.language'), 'attr') ?>">
    <?php foreach ($switchLocales as $i => $l): ?>
        <?= $i > 0 ? '<span aria-hidden="true">|</span>' : '' ?>
        <a class="small text-decoration-none" hreflang="<?= esc($l, 'attr') ?>" lang="<?= esc($l, 'attr') ?>" href="<?= esc(lang_switch_url($l, $page ?? []), 'attr') ?>"<?= $l === $currentLocale ? ' aria-current="true"' : '' ?>><?= esc(strtoupper($l)) ?></a>
    <?php endforeach; ?>
</nav>
