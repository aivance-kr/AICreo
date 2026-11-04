<?php
// SNS 링크 정의 — 아이콘만 노출되므로 접근 가능한 이름을 반드시 함께 낸다.
$socialLinks = [
    ['key' => 'instagram', 'icon' => 'bi-instagram', 'label' => lang('Site.snsInstagram')],
    ['key' => 'youtube',   'icon' => 'bi-youtube',   'label' => lang('Site.snsYoutube')],
    ['key' => 'blog',      'icon' => 'bi-rss',       'label' => lang('Site.snsBlog')],
    ['key' => 'kakao',     'icon' => 'bi-chat-fill', 'label' => lang('Site.snsKakao')],
];
?>
<footer class="bg-dark text-white mt-5 py-4<?= ($settings['active_theme'] ?? 'default') === 'blog' ? ' blog-footer' : '' ?>">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="fw-bold mb-2"><?= esc($settings['site_name'] ?? '') ?></div>
                <p class="text-white-50 small mb-0"><?= esc($settings['site_desc'] ?? '') ?></p>
            </div>
            <div class="col-md-4">
                <h2 class="fw-semibold mb-2 small text-uppercase text-white-50"><?= esc(lang('Site.contact')) ?></h2>
                <?php if (!empty($settings['phone'])): ?>
                    <div class="small"><i class="bi bi-telephone me-1" aria-hidden="true"></i><span class="visually-hidden"><?= esc(lang('Site.phone')) ?> </span><?= esc($settings['phone']) ?></div>
                <?php endif; ?>
                <?php if (!empty($settings['email'])): ?>
                    <div class="small"><i class="bi bi-envelope me-1" aria-hidden="true"></i><span class="visually-hidden"><?= esc(lang('Site.email')) ?> </span><?= esc($settings['email']) ?></div>
                <?php endif; ?>
                <?php if (!empty($settings['address'])): ?>
                    <div class="small"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><span class="visually-hidden"><?= esc(lang('Site.address')) ?> </span><?= esc($settings['address']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <h2 class="fw-semibold mb-2 small text-uppercase text-white-50">SNS</h2>
                <div class="d-flex gap-3">
                    <?php foreach ($socialLinks as $sns): ?>
                        <?php if (!empty($settings[$sns['key']])): ?>
                            <a href="<?= esc($settings[$sns['key']]) ?>" target="_blank" rel="noopener" class="text-secondary fs-5">
                                <i class="bi <?= esc($sns['icon']) ?>" aria-hidden="true"></i>
                                <span class="visually-hidden"><?= esc($sns['label']) ?> <?= esc(lang('Site.newWindow')) ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <a href="<?= esc(locale_url('rss'), 'attr') ?>" class="text-secondary fs-5">
                        <i class="bi bi-rss-fill" aria-hidden="true"></i>
                        <span class="visually-hidden"><?= esc(lang('Site.rssSubscribe')) ?></span>
                    </a>
                </div>
            </div>
        </div>
        <?php if (($settings['show_footer_newsletter'] ?? '1') === '1'): ?>
        <div class="row mt-4">
            <div class="col-md-6 position-relative">
                <?= view('themes/default/components/newsletter_form', ['inputId' => 'footer-newsletter-email', 'dark' => true]) ?>
            </div>
        </div>
        <?php endif; ?>
        <hr class="border-secondary mt-4">
        <div class="d-flex justify-content-between align-items-center small text-white-50">
            <span><?= esc($settings['copyright'] ?? '') ?></span>
            <?php if (!empty($settings['business_num'])): ?>
                <span><?= esc(lang('Site.businessNum')) ?>: <?= esc($settings['business_num']) ?></span>
            <?php endif; ?>
        </div>
    </div>
</footer>
