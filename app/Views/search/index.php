<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?= view('components/search_form', ['keyword' => $keyword]) ?>

<div class="container pb-4">
<?php if ($keyword === ''): ?>
    <p class="text-muted">검색어를 입력하세요.</p>
<?php elseif ($posts === []): ?>
    <p class="text-muted">"<?= esc($keyword) ?>"에 대한 검색 결과가 없습니다.</p>
<?php else: ?>
    <p class="text-muted small">"<?= esc($keyword) ?>" 검색 결과 <?= number_format($total) ?>건</p>
    <div class="list-group list-group-flush border-top">
        <?php foreach ($posts as $post): ?>
        <a href="/board/<?= esc($post['board_slug'], 'attr') ?>/<?= (int) $post['id'] ?>" class="list-group-item list-group-item-action py-3">
            <div class="small text-muted"><?= esc($post['board_name']) ?> · <?= esc(substr((string) $post['created_at'], 0, 10)) ?></div>
            <div class="fw-semibold"><?= esc($post['title']) ?></div>
            <div class="small text-muted"><?= esc(post_excerpt($post['content'], 120)) ?></div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php if ($totalPages > 1): ?>
    <nav class="d-flex justify-content-center mt-3" aria-label="페이지 목록">
        <ul class="pagination pagination-sm">
            <?php for ($p = max(1, $currentPage - 4); $p <= min($totalPages, $currentPage + 5); $p++): ?>
            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?q=<?= esc(urlencode($keyword), 'attr') ?>&page=<?= $p ?>"<?= $p === $currentPage ? ' aria-current="page"' : '' ?>><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
<?php endif; ?>
</div>

<?= $this->endSection() ?>
