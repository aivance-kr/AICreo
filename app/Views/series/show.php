<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container py-4">
    <h1 class="board-view-title fw-bold mb-1">
        <?= esc($series['title']) ?>
        <span class="badge text-bg-secondary align-middle"><?= esc(\App\Models\SeriesModel::STATUSES[$series['status']] ?? $series['status']) ?></span>
    </h1>
    <?php if (! empty($series['description'])): ?>
    <p class="text-muted"><?= nl2br(esc($series['description'])) ?></p>
    <?php endif; ?>

    <?php if ($posts === []): ?>
        <p class="text-muted">아직 공개된 글이 없습니다.</p>
    <?php else: ?>
    <p class="small text-muted">총 <?= count($posts) ?>편</p>
    <ol class="list-group list-group-numbered">
        <?php foreach ($posts as $p): ?>
        <li class="list-group-item">
            <a href="/board/<?= esc($p['board_slug'], 'attr') ?>/<?= (int) $p['id'] ?>" class="text-decoration-none"><?= esc($p['title']) ?></a>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
