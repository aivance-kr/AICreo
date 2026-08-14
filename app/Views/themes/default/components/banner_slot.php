<?php if (!empty($banners)): ?>
<div class="container py-3">
    <div class="main-banner-wrap">
        <?php foreach ($banners as $b): ?>
        <?php $alt = trim((string) ($b['alt_text'] ?? '')); ?>
        <?php if ($b['link_url']): ?>
        <a href="<?= esc($b['link_url']) ?>" target="<?= esc($b['link_target']) ?>"<?= $b['link_target'] === '_blank' ? ' rel="noopener"' : '' ?>>
            <img src="/<?= esc($b['image_path']) ?>" alt="<?= esc($alt !== '' ? $alt : '배너') ?>">
            <?php if ($b['link_target'] === '_blank'): ?><span class="visually-hidden">(새 창 열림)</span><?php endif; ?>
        </a>
        <?php else: ?>
        <img src="/<?= esc($b['image_path']) ?>" alt="<?= esc($alt) ?>">
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
