<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '일별 접속 상세' ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0"><?= esc($date) ?> 방문 기록 <span class="badge bg-light text-dark border"><?= number_format($total) ?>건</span></h2>
    <a href="<?= base_url('admin/stats') ?>" class="btn btn-sm btn-outline-secondary">통계로 돌아가기</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr><th>시각</th><th>페이지</th><th>IP</th><th>유입(Referer)</th><th>User-Agent</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (empty($visits)): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">방문 기록이 없습니다</td></tr>
                <?php endif; ?>
                <?php foreach ($visits as $v): ?>
                <tr>
                    <td class="text-nowrap"><?= esc(substr((string) $v['created_at'], 11)) ?></td>
                    <td class="text-break"><?= esc($v['uri']) ?></td>
                    <td class="text-nowrap"><?= esc($v['ip_address']) ?></td>
                    <td class="text-break"><?= $v['referer'] !== null && $v['referer'] !== '' ? esc($v['referer']) : '<span class="text-muted">직접 접속</span>' ?></td>
                    <td class="text-break"><?= esc((string) $v['user_agent']) ?></td>
                    <td class="text-nowrap">
                        <form method="post" action="<?= base_url('admin/stats/block-ip') ?>" onsubmit="return confirm('<?= esc($v['ip_address'], 'js') ?> 을(를) 30일간 차단할까요?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="ip" value="<?= esc($v['ip_address']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger py-0">차단</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3" aria-label="페이지 이동">
    <ul class="pagination pagination-sm justify-content-center">
        <?php
        // 현재 페이지가 속한 10개 단위 블록만 노출
        $blockSize = 10;
        $startPage = intdiv($currentPage - 1, $blockSize) * $blockSize + 1;
        $endPage   = min($startPage + $blockSize - 1, $totalPages);
        ?>
        <?php if ($startPage > 1): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?= $startPage - 1 ?>">&laquo;</a>
        </li>
        <?php endif; ?>
        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($endPage < $totalPages): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?= $endPage + 1 ?>">&raquo;</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?= $this->endSection() ?>
