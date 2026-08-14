<?php
// SNS 링크 정의 — 아이콘만 노출되므로 접근 가능한 이름을 반드시 함께 낸다.
$socialLinks = [
    ['key' => 'instagram', 'icon' => 'bi-instagram', 'label' => '인스타그램'],
    ['key' => 'youtube',   'icon' => 'bi-youtube',   'label' => '유튜브'],
    ['key' => 'blog',      'icon' => 'bi-rss',       'label' => '블로그'],
    ['key' => 'kakao',     'icon' => 'bi-chat-fill', 'label' => '카카오톡'],
];
?>
<footer class="bg-dark text-white mt-5 py-4">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="fw-bold mb-2"><?= esc($settings['site_name'] ?? '') ?></div>
                <p class="text-white-50 small mb-0"><?= esc($settings['site_desc'] ?? '') ?></p>
            </div>
            <div class="col-md-4">
                <h2 class="fw-semibold mb-2 small text-uppercase text-white-50">연락처</h2>
                <?php if (!empty($settings['phone'])): ?>
                    <div class="small"><i class="bi bi-telephone me-1" aria-hidden="true"></i><span class="visually-hidden">전화 </span><?= esc($settings['phone']) ?></div>
                <?php endif; ?>
                <?php if (!empty($settings['email'])): ?>
                    <div class="small"><i class="bi bi-envelope me-1" aria-hidden="true"></i><span class="visually-hidden">이메일 </span><?= esc($settings['email']) ?></div>
                <?php endif; ?>
                <?php if (!empty($settings['address'])): ?>
                    <div class="small"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><span class="visually-hidden">주소 </span><?= esc($settings['address']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <h2 class="fw-semibold mb-2 small text-uppercase text-white-50">SNS</h2>
                <div class="d-flex gap-3">
                    <?php foreach ($socialLinks as $sns): ?>
                        <?php if (!empty($settings[$sns['key']])): ?>
                            <a href="<?= esc($settings[$sns['key']]) ?>" target="_blank" rel="noopener" class="text-secondary fs-5">
                                <i class="bi <?= esc($sns['icon']) ?>" aria-hidden="true"></i>
                                <span class="visually-hidden"><?= esc($sns['label']) ?> (새 창 열림)</span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <hr class="border-secondary mt-4">
        <div class="d-flex justify-content-between align-items-center small text-white-50">
            <span><?= esc($settings['copyright'] ?? '') ?></span>
            <?php if (!empty($settings['business_num'])): ?>
                <span>사업자번호: <?= esc($settings['business_num']) ?></span>
            <?php endif; ?>
        </div>
    </div>
</footer>
