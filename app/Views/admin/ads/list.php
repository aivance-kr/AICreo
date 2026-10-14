<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '광고 관리' ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted small">총 <?= count($ads) ?>개</span>
    <a href="/admin/ads/create" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>광고 등록
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle table-stack">
            <thead class="table-light">
                <tr>
                    <th scope="col">이름</th>
                    <th scope="col">위치</th>
                    <th scope="col">우선순위</th>
                    <th scope="col">상태</th>
                    <th scope="col"><span class="visually-hidden">관리</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ads)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">등록된 광고가 없습니다.</td></tr>
                <?php endif; ?>
                <?php foreach ($ads as $ad): ?>
                <tr>
                    <td data-label="이름"><?= esc($ad['name']) ?></td>
                    <td data-label="위치"><span class="badge bg-secondary"><?= esc($positions[$ad['position']] ?? $ad['position']) ?></span></td>
                    <td data-label="우선순위"><?= (int) $ad['priority'] ?></td>
                    <td data-label="상태">
                        <?= $ad['is_active']
                            ? '<span class="badge bg-success">운영 중</span>'
                            : '<span class="badge bg-secondary">미운영</span>' ?>
                    </td>
                    <td data-label="" class="cell-actions text-end">
                        <a href="/admin/ads/<?= $ad['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">수정</a>
                        <form method="post" action="/admin/ads/<?= $ad['id'] ?>/delete" class="d-inline"
                              onsubmit="return confirm('광고를 삭제하시겠습니까?')">
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
