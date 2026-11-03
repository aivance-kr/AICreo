<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '글 수정 이력' ?>
<?= $this->section('content') ?>

<div class="mb-3 small">
    <a href="/board/<?= esc($boardSlug, 'attr') ?>/<?= (int) $post['id'] ?>/edit" class="text-decoration-none">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> 글 수정으로 돌아가기
    </a>
</div>

<h2 class="h6 fw-semibold mb-1"><?= esc($post['title']) ?></h2>
<p class="text-muted small">
    수정 저장으로 덮어쓰이기 직전의 제목·본문·메타 설명을 글당 최근 <?= \App\Models\PostRevisionModel::MAX_PER_POST ?>개까지 보관합니다.
    복구하면 복구 직전 글도 새 이력으로 남아 다시 되돌릴 수 있습니다.
</p>

<?php if ($revisions === []): ?>
    <p class="text-muted small">아직 수정 이력이 없습니다. 글을 고쳐 저장하면 이전 버전이 여기에 쌓입니다.</p>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-sm align-middle">
        <caption class="visually-hidden">수정 이력 목록</caption>
        <thead>
            <tr>
                <th scope="col">저장 시각</th>
                <th scope="col">제목</th>
                <th scope="col">저장한 사람</th>
                <th scope="col"><span class="visually-hidden">관리</span></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($revisions as $rev): ?>
            <tr>
                <td class="text-nowrap"><?= esc($rev['created_at']) ?></td>
                <td><?= esc($rev['title']) ?></td>
                <td><?= esc($rev['user_nickname'] ?? '비회원') ?></td>
                <td class="text-end text-nowrap">
                    <a href="/admin/posts/<?= (int) $post['id'] ?>/revisions/<?= (int) $rev['id'] ?>" class="btn btn-outline-secondary btn-sm">현재와 비교</a>
                    <form method="post" action="/admin/posts/<?= (int) $post['id'] ?>/revisions/<?= (int) $rev['id'] ?>/restore" class="d-inline"
                          onsubmit="return confirm('이 버전으로 복구할까요? 지금 글은 이력에 남습니다.')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-primary btn-sm">복구</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
