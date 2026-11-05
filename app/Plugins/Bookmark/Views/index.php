<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container py-4">
    <h1 class="fw-bold mb-3">내 북마크 <small class="text-muted fs-6"><?= number_format($total) ?>개</small></h1>

    <?php if ($items === []): ?>
        <p class="text-muted">저장한 글이 없습니다. 글 하단의 북마크 버튼으로 읽을 글을 모아 보세요.</p>
    <?php else: ?>
    <ul class="list-group mb-3">
        <?php foreach ($items as $item): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
            <div class="min-w-0">
                <a href="/board/<?= esc($item['board_slug'], 'url') ?>/<?= (int) $item['post_id'] ?>" class="fw-semibold text-decoration-none"><?= esc($item['title']) ?></a>
                <div class="small text-muted"><?= esc($item['board_name']) ?> · <?= esc(substr($item['created_at'], 0, 10)) ?> 저장</div>
            </div>
            <form method="post" action="/my/bookmarks/toggle/<?= (int) $item['post_id'] ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="return" value="list">
                <button class="btn btn-sm btn-outline-secondary">해제</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($lastPage > 1): ?>
    <nav class="d-flex justify-content-center gap-2" aria-label="페이지">
        <?php if ($pageNo > 1): ?><a class="btn btn-sm btn-outline-secondary" href="?page=<?= $pageNo - 1 ?>">이전</a><?php endif; ?>
        <span class="align-self-center small text-muted"><?= $pageNo ?> / <?= $lastPage ?></span>
        <?php if ($pageNo < $lastPage): ?><a class="btn btn-sm btn-outline-secondary" href="?page=<?= $pageNo + 1 ?>">다음</a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
