<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '팝업 관리' ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted small">총 <?= count($popups) ?>개</span>
    <a href="/admin/popups/create" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>팝업 등록
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle table-stack">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="width:80px">미리보기</th>
                    <th scope="col">제목</th>
                    <th scope="col">노출 범위</th>
                    <th scope="col">좌표</th>
                    <th scope="col">우선순위</th>
                    <th scope="col">시작일</th>
                    <th scope="col">종료일</th>
                    <th scope="col">상태</th>
                    <th scope="col"><span class="visually-hidden">관리</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($popups)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">등록된 팝업이 없습니다.</td></tr>
                <?php endif; ?>
                <?php foreach ($popups as $p): ?>
                <tr>
                    <td data-label="미리보기">
                        <?php if ($p['image_path']): ?>
                        <img loading="lazy" src="/<?= esc($p['image_path']) ?>" alt=""
                             style="width:70px;height:40px;object-fit:cover;border-radius:4px">
                        <?php else: ?>
                        <span class="text-muted small">텍스트</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="제목"><?= esc($p['title']) ?></td>
                    <td data-label="노출 범위"><span class="badge bg-secondary"><?= esc($scopes[$p['show_scope']] ?? $p['show_scope']) ?></span></td>
                    <td data-label="좌표" class="small text-muted">X:<?= (int)$p['pos_x'] ?> Y:<?= (int)$p['pos_y'] ?></td>
                    <td data-label="우선순위"><?= (int)$p['priority'] ?></td>
                    <td data-label="시작일" class="small text-muted"><?= $p['started_at'] ? substr($p['started_at'], 0, 10) : '—' ?></td>
                    <td data-label="종료일" class="small text-muted"><?= $p['ended_at']   ? substr($p['ended_at'],   0, 10) : '—' ?></td>
                    <td data-label="상태">
                        <?= $p['is_active']
                            ? '<span class="badge bg-success">운영 중</span>'
                            : '<span class="badge bg-secondary">미운영</span>' ?>
                    </td>
                    <td data-label="" class="cell-actions text-end">
                        <a href="/admin/popups/<?= $p['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">수정</a>
                        <form method="post" action="/admin/popups/<?= $p['id'] ?>/delete" class="d-inline"
                              onsubmit="return confirm('팝업을 삭제하시겠습니까?')">
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

<?= $this->endSection() ?>
