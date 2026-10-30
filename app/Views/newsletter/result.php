<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container py-5 text-center">
    <h1 class="h3 fw-bold mb-3"><?= esc($heading) ?></h1>
    <p class="text-muted mb-4"><?= esc($message) ?></p>
    <a href="/" class="btn btn-outline-secondary">홈으로</a>
</div>

<?= $this->endSection() ?>
