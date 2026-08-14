<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '배너 관리' ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted small">총 <?= count($banners) ?>개</span>
    <a href="/admin/banners/create" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>배너 등록
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle table-stack">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="width:80px">썸네일</th>
                    <th scope="col">위치</th>
                    <th scope="col">우선순위</th>
                    <th scope="col">링크</th>
                    <th scope="col">시작일</th>
                    <th scope="col">종료일</th>
                    <th scope="col">상태</th>
                    <th scope="col"><span class="visually-hidden">관리</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($banners)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">등록된 배너가 없습니다.</td></tr>
                <?php endif; ?>
                <?php foreach ($banners as $b): ?>
                <tr>
                    <td data-label="썸네일">
                        <img loading="lazy" src="/<?= esc($b['image_path']) ?>" alt=""
                             style="width:70px;height:40px;object-fit:cover;border-radius:4px">
                    </td>
                    <td data-label="위치"><span class="badge bg-secondary"><?= esc($positions[$b['position']] ?? $b['position']) ?></span></td>
                    <td data-label="우선순위"><?= (int)$b['priority'] ?></td>
                    <td data-label="링크" class="small text-muted">
                        <?php if ($b['link_url']): ?>
                            <a href="<?= esc($b['link_url']) ?>" target="<?= esc($b['link_target']) ?>" class="text-truncate d-inline-block" style="max-width:160px">
                                <?= esc($b['link_url']) ?>
                            </a>
                            <span class="text-muted">(<?= $b['link_target'] === '_blank' ? '새창' : '현재창' ?>)</span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td data-label="시작일" class="small text-muted"><?= $b['started_at'] ? substr($b['started_at'], 0, 10) : '—' ?></td>
                    <td data-label="종료일" class="small text-muted"><?= $b['ended_at']   ? substr($b['ended_at'],   0, 10) : '—' ?></td>
                    <td data-label="상태">
                        <?= $b['is_active']
                            ? '<span class="badge bg-success">운영 중</span>'
                            : '<span class="badge bg-secondary">미운영</span>' ?>
                    </td>
                    <td data-label="" class="cell-actions text-end">
                        <a href="/admin/banners/<?= $b['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">수정</a>
                        <form method="post" action="/admin/banners/<?= $b['id'] ?>/delete" class="d-inline"
                              onsubmit="return confirm('배너를 삭제하시겠습니까?')">
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
