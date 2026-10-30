<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '뉴스레터 구독자' ?>

<?= $this->section('content') ?>

<?php
$statusLabels = ['pending' => '확인 대기', 'confirmed' => '구독 중', 'unsubscribed' => '해지'];
$statusBadges = ['pending' => 'bg-warning text-dark', 'confirmed' => 'bg-success', 'unsubscribed' => 'bg-secondary'];
$filterQs     = http_build_query(array_filter(['q' => $keyword, 'status' => $status], static fn (string $v): bool => $v !== ''));
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <form method="get" class="d-flex flex-wrap gap-2" role="search">
        <label class="visually-hidden" for="subscriber-search">이메일 검색</label>
        <input type="search" name="q" id="subscriber-search" class="form-control form-control-sm" placeholder="이메일 검색"
               value="<?= esc($keyword) ?>" autocomplete="off" spellcheck="false">
        <label class="visually-hidden" for="subscriber-status">상태</label>
        <select name="status" id="subscriber-status" class="form-select form-select-sm w-auto">
            <option value="">전체 상태</option>
            <?php foreach ($statusLabels as $value => $label): ?>
            <option value="<?= $value ?>"<?= $status === $value ? ' selected' : '' ?>><?= $label ?> (<?= number_format($statusCounts[$value]) ?>)</option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-secondary">검색</button>
        <?php if ($keyword !== '' || $status !== ''): ?>
        <a href="<?= base_url('admin/subscribers') ?>" class="btn btn-sm btn-outline-secondary">초기화</a>
        <?php endif; ?>
    </form>
    <div class="d-flex align-items-center gap-3">
        <span class="text-muted small">총 <?= number_format($total) ?>명</span>
        <a href="<?= base_url('admin/subscribers/export') . ($filterQs !== '' ? '?' . esc($filterQs, 'attr') : '') ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-download me-1" aria-hidden="true"></i>CSV 내보내기
        </a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 table-stack">
            <thead class="table-light">
                <tr>
                    <th scope="col">이메일</th>
                    <th scope="col">상태</th>
                    <th scope="col">신청일</th>
                    <th scope="col">확인일</th>
                    <th scope="col"><span class="visually-hidden">관리</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">구독자가 없습니다.</td></tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td data-label="이메일"><?= esc($item['email']) ?></td>
                    <td data-label="상태"><span class="badge <?= $statusBadges[$item['status']] ?? 'bg-secondary' ?>"><?= esc($statusLabels[$item['status']] ?? $item['status']) ?></span></td>
                    <td data-label="신청일" class="small text-muted"><?= esc(date('Y-m-d H:i', strtotime((string) $item['created_at']))) ?></td>
                    <td data-label="확인일" class="small text-muted"><?= $item['confirmed_at'] ? esc(date('Y-m-d H:i', strtotime((string) $item['confirmed_at']))) : '-' ?></td>
                    <td data-label="" class="cell-actions">
                        <form method="post" action="/admin/subscribers/<?= (int) $item['id'] ?>/delete" class="d-inline"
                              onsubmit="return confirm('<?= esc($item['email'], 'js') ?> 구독자를 삭제하시겠습니까?')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3" aria-label="구독자 페이지 이동">
    <ul class="pagination pagination-sm justify-content-center">
        <?php $qs = $filterQs !== '' ? '&' . $filterQs : ''; ?>
        <?php
        // 현재 페이지가 속한 10개 단위 블록만 노출
        $blockSize = 10;
        $startPage = intdiv($currentPage - 1, $blockSize) * $blockSize + 1;
        $endPage   = min($startPage + $blockSize - 1, $totalPages);
        ?>
        <?php if ($startPage > 1): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?= $startPage - 1 ?><?= $qs ?>" aria-label="이전 페이지 묶음">&laquo;</a>
        </li>
        <?php endif; ?>
        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?><?= $qs ?>"<?= $i === $currentPage ? ' aria-current="page"' : '' ?>><?= $i ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($endPage < $totalPages): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?= $endPage + 1 ?><?= $qs ?>" aria-label="다음 페이지 묶음">&raquo;</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?= $this->endSection() ?>
