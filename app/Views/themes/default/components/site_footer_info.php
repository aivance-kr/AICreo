<?php
/**
 * 블로그형 테마 공용 푸터 정보 블록 — 사이트 설명·연락처·SNS·저작권.
 * 레이아웃(색·배치)은 각 테마 CSS 가 정한다.
 */
$socialLinks = [
    ['key' => 'instagram', 'icon' => 'bi-instagram', 'label' => '인스타그램'],
    ['key' => 'youtube',   'icon' => 'bi-youtube',   'label' => '유튜브'],
    ['key' => 'blog',      'icon' => 'bi-rss',       'label' => '블로그'],
    ['key' => 'kakao',     'icon' => 'bi-chat-fill', 'label' => '카카오톡'],
];
?>
<div class="site-footer__info">
    <?php if (! empty($settings['site_desc'])): ?>
    <p class="site-footer__desc"><?= esc($settings['site_desc']) ?></p>
    <?php endif; ?>
    <ul class="site-footer__contact">
        <?php if (! empty($settings['email'])): ?><li><span class="visually-hidden">이메일 </span><?= esc($settings['email']) ?></li><?php endif; ?>
        <?php if (! empty($settings['phone'])): ?><li><span class="visually-hidden">전화 </span><?= esc($settings['phone']) ?></li><?php endif; ?>
        <?php if (! empty($settings['address'])): ?><li><span class="visually-hidden">주소 </span><?= esc($settings['address']) ?></li><?php endif; ?>
    </ul>
    <ul class="site-footer__sns">
        <?php foreach ($socialLinks as $sns): ?>
            <?php if (! empty($settings[$sns['key']])): ?>
            <li><a href="<?= esc($settings[$sns['key']]) ?>" target="_blank" rel="noopener"><i class="bi <?= esc($sns['icon']) ?>" aria-hidden="true"></i><span class="visually-hidden"><?= esc($sns['label']) ?> (새 창 열림)</span></a></li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li><a href="<?= esc(site_url('rss'), 'attr') ?>"><i class="bi bi-rss-fill" aria-hidden="true"></i><span class="visually-hidden">RSS 구독</span></a></li>
    </ul>
    <div class="site-footer__newsletter position-relative mb-3">
        <?= view('themes/default/components/newsletter_form', [
            'inputId' => 'footer-newsletter-email',
            'dark'    => in_array($settings['active_theme'] ?? 'default', ['cobalt', 'devnotes'], true),
        ]) ?>
    </div>
    <p class="site-footer__copy">
        <?= esc($settings['copyright'] ?? '') ?>
        <?php if (! empty($settings['business_num'])): ?> · 사업자번호 <?= esc($settings['business_num']) ?><?php endif; ?>
    </p>
</div>
