<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container py-4">
    <p class="small mb-1"><a href="/tags" class="text-decoration-none">← 전체 태그</a></p>
    <h1 class="board-view-title fw-bold mb-1">#<?= esc($tag) ?></h1>
    <p class="text-muted small"><?= number_format($total) ?>개의 글</p>

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
                <a class="page-link" href="?page=<?= $p ?>"<?= $p === $currentPage ? ' aria-current="page"' : '' ?>><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
