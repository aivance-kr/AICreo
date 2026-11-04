<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$byYear = [];
foreach ($counts as $row) {
    $byYear[$row['year']][] = $row;
}
?>
<div class="container py-4">
    <h1 class="board-view-title fw-bold mb-3"><?= esc(lang('Content.archive')) ?></h1>

    <?php if ($byYear === []): ?>
        <p class="text-muted"><?= esc(lang('Content.noPublicPosts')) ?></p>
    <?php else: ?>
        <?php foreach ($byYear as $year => $months): ?>
        <h2 class="h5 mt-4"><?= esc(lang('Content.yearLabel', ['year' => (string) (int) $year])) ?> <span class="small text-muted"><?= esc(lang('Content.postCount', ['count' => array_sum(array_column($months, 'count'))])) ?></span></h2>
        <ul class="list-group">
            <?php foreach ($months as $row): ?>
            <li class="list-group-item d-flex justify-content-between">
                <a href="/archive/<?= (int) $row['year'] ?>/<?= sprintf('%02d', $row['month']) ?>" class="text-decoration-none"><?= esc(lang('Content.monthName', ['month' => (int) $row['month'], 'monthName' => date('F', mktime(0, 0, 0, (int) $row['month'], 1))])) ?></a>
                <span class="small text-muted"><?= esc(lang('Content.postCount', ['count' => (int) $row['count']])) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
