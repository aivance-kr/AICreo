<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container py-4">
    <h1 class="h4 mb-3"><?= esc(lang('Board.chooseBoard')) ?></h1>
    <div class="list-group">
        <?php foreach ($boards as $board): ?>
        <a href="/board/<?= esc($board['slug']) ?>/write" class="list-group-item list-group-item-action">
            <span class="fw-semibold"><?= esc($board['name']) ?></span>
            <?php if (! empty($board['description'])): ?>
            <span class="d-block small text-muted"><?= esc($board['description']) ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?= $this->endSection() ?>
