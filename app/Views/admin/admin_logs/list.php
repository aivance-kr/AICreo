<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '활동 로그' ?>

<?= $this->section('content') ?>

<?php
use App\Models\AdminLogModel;

$filters = array_filter(['action' => $action, 'target_type' => $targetType, 'q' => $keyword], static fn (string $v): bool => $v !== '');
$qs      = $filters === [] ? '' : '&' . http_build_query($filters);
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <form method="get" class="d-flex flex-wrap gap-2" role="search">
        <label class="visually-hidden" for="log-target">대상</label>
        <select name="target_type" id="log-target" class="form-select form-select-sm w-auto">
            <option value="">전체 대상</option>
            <?php foreach (AdminLogModel::TARGET_TYPES as $value => $label): ?>
            <option value="<?= esc($value) ?>" <?= $targetType === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="visually-hidden" for="log-action">동작</label>
        <select name="action" id="log-action" class="form-select form-select-sm w-auto">
            <option value="">전체 동작</option>
            <?php foreach (AdminLogModel::ACTIONS as $value => $label): ?>
            <option value="<?= esc($value) ?>" <?= $action === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="visually-hidden" for="log-search">검색</label>
        <input type="search" name="q" id="log-search" class="form-control form-control-sm w-auto" placeholder="요약·IP·이름 검색"
               value="<?= esc($keyword) ?>" autocomplete="off" spellcheck="false">
        <button type="submit" class="btn btn-sm btn-secondary">검색</button>
        <?php if ($filters !== []): ?>
        <a href="<?= base_url('admin/logs') ?>" class="btn btn-sm btn-outline-secondary">초기화</a>
        <?php endif; ?>
    </form>
    <span class="text-muted small">총 <?= number_format($total) ?>건</span>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 table-stack">
            <thead class="table-light">
                <tr>
                    <th scope="col">일시</th>
                    <th scope="col">관리자</th>
                    <th scope="col">대상</th>
                    <th scope="col">동작</th>
                    <th scope="col">내용</th>
                    <th scope="col">IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">기록된 활동이 없습니다.</td></tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td data-label="일시" class="small text-muted"><?= esc(date('Y-m-d H:i:s', strtotime((string) $item['created_at']))) ?></td>
                    <td data-label="관리자"><?= $item['user_id'] === null ? '<span class="text-muted">시스템</span>' : esc($item['nickname'] ?? '#' . $item['user_id']) ?></td>
                    <td data-label="대상">
                        <?= esc(AdminLogModel::TARGET_TYPES[$item['target_type']] ?? $item['target_type']) ?>
                        <?php if ($item['target_id'] !== null): ?><span class="text-muted small">#<?= (int) $item['target_id'] ?></span><?php endif; ?>
                    </td>
                    <td data-label="동작"><span class="badge bg-secondary"><?= esc(AdminLogModel::ACTIONS[$item['action']] ?? $item['action']) ?></span></td>
                    <td data-label="내용" class="small"><?= esc((string) $item['summary']) ?></td>
                    <td data-label="IP" class="font-monospace small"><?= esc((string) $item['ip_address']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3" aria-label="활동 로그 페이지 이동">
    <ul class="pagination pagination-sm justify-content-center">
        <?php
        $blockSize = 10;
        $startPage = intdiv($currentPage - 1, $blockSize) * $blockSize + 1;
        $endPage   = min($startPage + $blockSize - 1, $totalPages);
        ?>
        <?php if ($startPage > 1): ?>
        <li class="page-item"><a class="page-link" href="?page=<?= $startPage - 1 ?><?= $qs ?>" aria-label="이전 페이지 묶음">&laquo;</a></li>
        <?php endif; ?>
        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?><?= $qs ?>"<?= $i === $currentPage ? ' aria-current="page"' : '' ?>><?= $i ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($endPage < $totalPages): ?>
        <li class="page-item"><a class="page-link" href="?page=<?= $endPage + 1 ?><?= $qs ?>" aria-label="다음 페이지 묶음">&raquo;</a></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?= $this->endSection() ?>
