<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '게시판 관리' ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between mb-3">
    <h5>게시판 관리</h5>
    <a href="/admin/boards/create" class="btn btn-primary btn-sm">+ 게시판 추가</a>
</div>
<table class="table table-hover board-table table-stack">
    <thead>
        <tr>
            <th scope="col">순서</th><th scope="col">게시판명</th><th scope="col">슬러그</th>
            <th scope="col">읽기권한</th><th scope="col">쓰기권한</th><th scope="col">파일</th><th scope="col">이미지</th><th scope="col">상태</th><th scope="col"><span class="visually-hidden">관리</span></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($boards as $b): ?>
        <tr>
            <td data-label="순서"><?= $b['sort_order'] ?></td>
            <td data-label="게시판명"><?= esc($b['name']) ?></td>
            <td data-label="슬러그"><code>/board/<?= esc($b['slug']) ?></code></td>
            <td data-label="읽기권한"><?= $b['read_permission'] ?></td>
            <td data-label="쓰기권한"><?= $b['write_permission'] ?></td>
            <td data-label="파일"><?= $b['allow_file'] ? '✓' : '-' ?></td>
            <td data-label="이미지"><?= $b['allow_image'] ? '✓' : '-' ?></td>
            <td data-label="상태"><?= $b['is_active'] ? '<span class="badge bg-success">활성</span>' : '<span class="badge bg-secondary">비활성</span>' ?></td>
            <td data-label="" class="cell-actions">
                <a href="/admin/boards/<?= $b['id'] ?>/edit" class="btn btn-xs btn-outline-secondary btn-sm">수정</a>
                <a href="/admin/boards/<?= $b['id'] ?>/posts" class="btn btn-xs btn-outline-primary btn-sm">게시글</a>
                <a href="/board/<?= esc($b['slug']) ?>" target="_blank" rel="noopener" class="btn btn-xs btn-outline-dark btn-sm">미리보기</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?= $this->endSection() ?>
