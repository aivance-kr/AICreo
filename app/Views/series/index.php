<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container py-4">
    <h1 class="board-view-title fw-bold mb-3">시리즈</h1>

    <?php if ($seriesList === []): ?>
        <p class="text-muted">아직 공개된 시리즈가 없습니다.</p>
    <?php else: ?>
    <ul class="list-group">
        <?php foreach ($seriesList as $item): ?>
        <li class="list-group-item">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <a href="/series/<?= esc($item['slug'], 'attr') ?>" class="fw-semibold text-decoration-none"><?= esc($item['title']) ?></a>
                <span class="small text-muted text-nowrap">
                    <span class="badge text-bg-secondary"><?= esc(\App\Models\SeriesModel::STATUSES[$item['status']] ?? $item['status']) ?></span>
                    <?= (int) $item['post_count'] ?>편
                </span>
            </div>
            <?php if (! empty($item['description'])): ?>
            <p class="small text-muted mb-0 mt-1"><?= esc(mb_strimwidth(trim(strip_tags((string) $item['description'])), 0, 120, '…')) ?></p>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
