<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container py-4">
    <h1 class="board-view-title fw-bold mb-3">태그</h1>

    <?php if ($tags === []): ?>
        <p class="text-muted">아직 태그가 붙은 글이 없습니다.</p>
    <?php else: ?>
    <?php $max = max($tags); ?>
    <ul class="list-inline">
        <?php foreach ($tags as $name => $count): ?>
        <?php $size = $max > 1 && $count >= $max * 0.6 ? 'fs-4' : ($count > 1 ? 'fs-5' : 'fs-6'); ?>
        <li class="list-inline-item me-3 mb-2">
            <a href="/tags/<?= esc(rawurlencode((string) $name), 'attr') ?>" class="text-decoration-none <?= $size ?>">#<?= esc((string) $name) ?></a>
            <span class="small text-muted"><?= (int) $count ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
