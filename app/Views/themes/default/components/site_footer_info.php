<?php
/**
 * 블로그형 테마 공용 푸터 정보 블록 — 사이트 설명·연락처·SNS·저작권.
 * 레이아웃(색·배치)은 각 테마 CSS 가 정한다.
 */
$socialLinks = [
    ['key' => 'instagram', 'icon' => 'bi-instagram', 'label' => lang('Site.snsInstagram')],
    ['key' => 'youtube',   'icon' => 'bi-youtube',   'label' => lang('Site.snsYoutube')],
    ['key' => 'blog',      'icon' => 'bi-rss',       'label' => lang('Site.snsBlog')],
    ['key' => 'kakao',     'icon' => 'bi-chat-fill', 'label' => lang('Site.snsKakao')],
];
?>
<div class="site-footer__info">
    <?php if (! empty($settings['site_desc'])): ?>
    <p class="site-footer__desc"><?= esc($settings['site_desc']) ?></p>
    <?php endif; ?>
    <ul class="site-footer__contact">
        <?php if (! empty($settings['email'])): ?><li><span class="visually-hidden"><?= esc(lang('Site.email')) ?> </span><?= esc($settings['email']) ?></li><?php endif; ?>
        <?php if (! empty($settings['phone'])): ?><li><span class="visually-hidden"><?= esc(lang('Site.phone')) ?> </span><?= esc($settings['phone']) ?></li><?php endif; ?>
        <?php if (! empty($settings['address'])): ?><li><span class="visually-hidden"><?= esc(lang('Site.address')) ?> </span><?= esc($settings['address']) ?></li><?php endif; ?>
    </ul>
    <ul class="site-footer__sns">
        <?php foreach ($socialLinks as $sns): ?>
            <?php if (! empty($settings[$sns['key']])): ?>
            <li><a href="<?= esc($settings[$sns['key']]) ?>" target="_blank" rel="noopener"><i class="bi <?= esc($sns['icon']) ?>" aria-hidden="true"></i><span class="visually-hidden"><?= esc($sns['label']) ?> <?= esc(lang('Site.newWindow')) ?></span></a></li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li><a href="<?= esc(locale_url('rss'), 'attr') ?>"><i class="bi bi-rss-fill" aria-hidden="true"></i><span class="visually-hidden"><?= esc(lang('Site.rssSubscribe')) ?></span></a></li>
    </ul>
    <?php if (($settings['show_footer_newsletter'] ?? '1') === '1'): ?>
    <div class="site-footer__newsletter position-relative mb-3">
        <?= view('themes/default/components/newsletter_form', [
            'inputId' => 'footer-newsletter-email',
            'dark'    => in_array($settings['active_theme'] ?? 'default', ['cobalt', 'devnotes'], true),
        ]) ?>
    </div>
    <?php endif; ?>
    <p class="site-footer__copy">
        <?= esc($settings['copyright'] ?? '') ?>
        <?php if (! empty($settings['business_num'])): ?> · <?= esc(lang('Site.businessNum')) ?> <?= esc($settings['business_num']) ?><?php endif; ?>
    </p>
</div>
