<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$byYear = [];
foreach ($counts as $row) {
    $byYear[$row['year']][] = $row;
}
?>
<div class="container py-4">
    <h1 class="board-view-title fw-bold mb-3">아카이브</h1>

    <?php if ($byYear === []): ?>
        <p class="text-muted">아직 공개된 글이 없습니다.</p>
    <?php else: ?>
        <?php foreach ($byYear as $year => $months): ?>
        <h2 class="h5 mt-4"><?= (int) $year ?>년 <span class="small text-muted"><?= array_sum(array_column($months, 'count')) ?>편</span></h2>
        <ul class="list-group">
            <?php foreach ($months as $row): ?>
            <li class="list-group-item d-flex justify-content-between">
                <a href="/archive/<?= (int) $row['year'] ?>/<?= sprintf('%02d', $row['month']) ?>" class="text-decoration-none"><?= (int) $row['month'] ?>월</a>
                <span class="small text-muted"><?= (int) $row['count'] ?>편</span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
