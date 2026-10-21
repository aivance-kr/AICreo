<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '차단 IP 관리' ?>

<?= $this->section('content') ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <form method="get" class="d-flex gap-2" role="search">
        <label class="visually-hidden" for="blocked-ip-search">IP 검색</label>
        <input type="search" name="q" id="blocked-ip-search" class="form-control form-control-sm" placeholder="IP 검색"
               value="<?= esc($keyword) ?>" autocomplete="off" spellcheck="false">
        <button type="submit" class="btn btn-sm btn-secondary">검색</button>
        <?php if ($keyword !== ''): ?>
        <a href="<?= base_url('admin/blocked-ips') ?>" class="btn btn-sm btn-outline-secondary">초기화</a>
        <?php endif; ?>
    </form>
    <span class="text-muted small">차단 중 <?= number_format($total) ?>건</span>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 table-stack">
            <thead class="table-light">
                <tr>
                    <th scope="col">IP</th>
                    <th scope="col">사유</th>
                    <th scope="col">차단일</th>
                    <th scope="col">해제 예정</th>
                    <th scope="col"><span class="visually-hidden">관리</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">
                    <?= $keyword !== '' ? '“' . esc($keyword) . '”와 일치하는 차단 IP 가 없습니다.' : '차단 중인 IP 가 없습니다.' ?>
                </td></tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td data-label="IP" class="font-monospace"><?= esc($item['ip_address']) ?></td>
                    <td data-label="사유">
                        <span class="badge <?= $item['reason'] === 'auto' ? 'bg-warning text-dark' : 'bg-secondary' ?>">
                            <?= $item['reason'] === 'auto' ? '자동' : '수동' ?>
                        </span>
                    </td>
                    <td data-label="차단일" class="small text-muted"><?= esc(date('Y-m-d H:i', strtotime((string) $item['created_at']))) ?></td>
                    <td data-label="해제 예정" class="small text-muted"><?= esc(date('Y-m-d H:i', strtotime($item['blocked_until']))) ?></td>
                    <td data-label="" class="cell-actions">
                        <form method="post" action="/admin/blocked-ips/<?= (int) $item['id'] ?>/delete" class="d-inline"
                              onsubmit="return confirm('<?= esc($item['ip_address'], 'js') ?> 차단을 해제하시겠습니까?')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">차단 해제</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3" aria-label="차단 IP 페이지 이동">
    <ul class="pagination pagination-sm justify-content-center">
        <?php $qs = $keyword !== '' ? '&' . http_build_query(['q' => $keyword]) : ''; ?>
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
