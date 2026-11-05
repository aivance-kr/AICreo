<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container py-4">
    <h1 class="fw-bold mb-3">샘플 플러그인</h1>

    <?php if ($notes === []): ?>
        <p class="text-muted">메모가 없습니다.</p>
    <?php else: ?>
    <ul class="list-group">
        <?php foreach ($notes as $note): ?>
        <li class="list-group-item"><?= esc($note['body']) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
