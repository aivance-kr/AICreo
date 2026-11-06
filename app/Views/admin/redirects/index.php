<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('pageTitle', '리다이렉트 관리') ?>
<?= $this->section('content') ?>

<?php
$formOld = old('old_path', $editing['old_path'] ?? $prefillOld);
$qs      = $keyword !== '' ? '&' . http_build_query(['q' => $keyword]) : '';
?>

<div class="alert alert-info small">
    옛 주소로 들어온 방문자를 새 주소로 보냅니다. 원 주소는 <code>/옛경로</code> 또는 <code>/?p=191</code> 처럼 쿼리스트링까지 정확히 일치해야 합니다.
    같은 원 주소를 두 번 등록하거나, 대상이 다시 원 주소로 돌아오는 순환 설정은 저장되지 않습니다.
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <form method="get" class="d-flex gap-2" role="search">
                <label class="visually-hidden" for="redirect-search">주소 검색</label>
                <input type="search" name="q" id="redirect-search" class="form-control form-control-sm" placeholder="주소 검색"
                       value="<?= esc($keyword) ?>" autocomplete="off" spellcheck="false">
                <button type="submit" class="btn btn-sm btn-secondary">검색</button>
                <?php if ($keyword !== ''): ?>
                <a href="/admin/redirects" class="btn btn-sm btn-outline-secondary">초기화</a>
                <?php endif; ?>
            </form>
            <span class="text-muted small">총 <?= number_format($total) ?>건</span>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 table-stack align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">원 주소</th>
                            <th scope="col">대상</th>
                            <th scope="col">종류</th>
                            <th scope="col" class="text-end">적중</th>
                            <th scope="col">상태</th>
                            <th scope="col"><span class="visually-hidden">관리</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">
                            <?= $keyword !== '' ? '“' . esc($keyword) . '”와 일치하는 리다이렉트가 없습니다.' : '등록된 리다이렉트가 없습니다.' ?>
                        </td></tr>
                        <?php endif; ?>
                        <?php foreach ($items as $item): ?>
                        <tr class="<?= (int) $item['is_active'] === 1 ? '' : 'text-muted' ?>">
                            <td data-label="원 주소" class="text-break font-monospace small"><?= esc($item['old_path']) ?></td>
                            <td data-label="대상" class="text-break font-monospace small"><?= esc($item['new_path']) ?></td>
                            <td data-label="종류"><?= (int) $item['status_code'] ?></td>
                            <td data-label="적중" class="text-end"><?= number_format((int) $item['hit_count']) ?></td>
                            <td data-label="상태">
                                <span class="badge <?= (int) $item['is_active'] === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= (int) $item['is_active'] === 1 ? '사용' : '사용 안 함' ?>
                                </span>
                            </td>
                            <td data-label="" class="cell-actions text-nowrap">
                                <a href="/admin/redirects?edit=<?= (int) $item['id'] ?><?= $qs ?>" class="btn btn-sm btn-outline-secondary">수정</a>
                                <form method="post" action="/admin/redirects/<?= (int) $item['id'] ?>/toggle" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?= (int) $item['is_active'] === 1 ? '끄기' : '켜기' ?></button>
                                </form>
                                <form method="post" action="/admin/redirects/<?= (int) $item['id'] ?>/delete" class="d-inline"
                                      onsubmit="return confirm('이 리다이렉트를 삭제할까요?')">
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
        <nav class="mt-3" aria-label="리다이렉트 페이지 이동">
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
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h6 fw-semibold mb-3"><?= $editing ? '리다이렉트 수정' : '새 리다이렉트 추가' ?></h2>
                <form method="post" action="/admin/redirects<?= $editing ? '/' . (int) $editing['id'] : '' ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="redirect-old">원 주소</label>
                        <input type="text" name="old_path" id="redirect-old" class="form-control form-control-sm font-monospace" maxlength="500"
                               placeholder="/old-page" value="<?= esc($formOld) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="redirect-new">대상</label>
                        <input type="text" name="new_path" id="redirect-new" class="form-control form-control-sm font-monospace" maxlength="500"
                               placeholder="/new-page 또는 https://…" value="<?= esc(old('new_path', $editing['new_path'] ?? '')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="redirect-code">종류</label>
                        <select name="status_code" id="redirect-code" class="form-select form-select-sm">
                            <?php foreach (\App\Models\RedirectModel::STATUS_CODES as $code => $label): ?>
                            <option value="<?= $code ?>" <?= (int) old('status_code', $editing['status_code'] ?? 301) === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="is_active" id="redirect-active" value="1" class="form-check-input"
                               <?= (old('is_active', $editing === null ? '1' : (string) $editing['is_active'])) === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="redirect-active">사용</label>
                    </div>
                    <div class="text-end">
                        <?php if ($editing): ?>
                        <a href="/admin/redirects" class="btn btn-outline-secondary btn-sm">취소</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-sm px-4"><?= $editing ? '수정' : '추가' ?></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <h2 class="h6 fw-semibold mb-1">최근 <?= (int) $notFoundDays ?>일 404 주소</h2>
                <p class="text-muted small">아직 리다이렉트가 없는 주소입니다. “등록”을 누르면 원 주소가 채워집니다.</p>
                <?php if ($notFound === []): ?>
                <p class="text-muted small mb-0">404 기록이 없습니다.</p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($notFound as $nf): ?>
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                        <span class="small text-break font-monospace"><?= esc($nf['uri']) ?> <span class="text-muted">× <?= number_format($nf['count']) ?></span></span>
                        <a href="/admin/redirects?old=<?= esc(rawurlencode($nf['uri']), 'attr') ?>" class="btn btn-sm btn-outline-primary text-nowrap">등록</a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
