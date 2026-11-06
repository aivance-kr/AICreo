<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('pageTitle', '휴지통') ?>

<?= $this->section('content') ?>

<ul class="nav nav-tabs mb-3">
    <?php foreach ($types as $key => $label): ?>
    <li class="nav-item">
        <a class="nav-link <?= $type === $key ? 'active' : '' ?>" href="<?= base_url('admin/trash') ?>?type=<?= esc($key, 'url') ?>"<?= $type === $key ? ' aria-current="page"' : '' ?>>
            <?= esc($label) ?> <span class="badge bg-secondary"><?= number_format($counts[$key]) ?></span>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<p class="text-muted small">삭제한 항목은 <?= (int) $retentionDays ?>일 동안 보관된 뒤 자동으로 영구 삭제됩니다. 복원하면 첨부 파일과 시리즈 소속이 그대로 돌아옵니다.</p>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 table-stack">
            <thead class="table-light">
                <tr>
                    <th scope="col">제목</th>
                    <th scope="col">위치</th>
                    <th scope="col">삭제일</th>
                    <th scope="col"><span class="visually-hidden">관리</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">휴지통이 비어 있습니다.</td></tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td data-label="제목"><?= esc($item['title']) ?></td>
                    <td data-label="위치" class="small text-muted"><?= esc($item['meta']) ?></td>
                    <td data-label="삭제일" class="small text-muted"><?= esc(date('Y-m-d H:i', strtotime($item['deleted_at']))) ?></td>
                    <td data-label="" class="cell-actions">
                        <form method="post" action="/admin/trash/<?= esc($type, 'attr') ?>/<?= (int) $item['id'] ?>/restore" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-primary">복원</button>
                        </form>
                        <form method="post" action="/admin/trash/<?= esc($type, 'attr') ?>/<?= (int) $item['id'] ?>/delete" class="d-inline"
                              onsubmit="return confirm('영구 삭제하면 되돌릴 수 없습니다. 삭제하시겠습니까?')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">영구 삭제</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3" aria-label="휴지통 페이지 이동">
    <ul class="pagination pagination-sm justify-content-center">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
            <a class="page-link" href="?type=<?= esc($type, 'url') ?>&page=<?= $i ?>"<?= $i === $currentPage ? ' aria-current="page"' : '' ?>><?= $i ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?= $this->endSection() ?>
