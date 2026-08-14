<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '페이지 관리' ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between mb-3">
    <div></div>
    <a href="/admin/pages/create" class="btn btn-primary btn-sm">+ 페이지 추가</a>
</div>

<div class="card border-0 shadow-sm">
    <table class="table table-hover mb-0 table-stack">
        <thead class="table-light">
            <tr><th scope="col">순서</th><th scope="col">제목</th><th scope="col">슬러그</th><th scope="col">레이아웃</th><th scope="col">상태</th><th scope="col"><span class="visually-hidden">관리</span></th></tr>
        </thead>
        <tbody>
            <?php foreach ($pages as $p): ?>
            <tr>
                <td data-label="순서"><?= $p['sort_order'] ?></td>
                <td data-label="제목"><?= esc($p['title']) ?></td>
                <td data-label="슬러그"><code>/<?= esc($p['slug']) ?></code></td>
                <td data-label="레이아웃"><span class="badge bg-secondary"><?= esc($p['layout']) ?></span></td>
                <td data-label="상태"><?= $p['status'] === 'published' ? '<span class="badge bg-success">공개</span>' : '<span class="badge bg-warning">초안</span>' ?></td>
                <td data-label="" class="text-end cell-actions">
                    <a href="/<?= esc($p['slug']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark">미리보기</a>
                    <a href="/admin/pages/<?= $p['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">수정</a>
                    <form method="post" action="/admin/pages/<?= $p['id'] ?>/delete" class="d-inline" onsubmit="return confirm('<?= esc($p['title'], 'js') ?> 페이지를 삭제하시겠습니까?')">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-danger">삭제</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>
