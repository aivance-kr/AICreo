<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('pageTitle', '미리보기 공유 링크') ?>
<?= $this->section('content') ?>

<div class="mb-3 small">
    <a href="/board/<?= esc($boardSlug, 'attr') ?>/<?= (int) $post['id'] ?>/edit" class="text-decoration-none">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> 글 수정으로 돌아가기
    </a>
</div>

<h2 class="h6 fw-semibold mb-1"><?= esc($post['title']) ?></h2>
<p class="text-muted small">
    링크를 아는 사람은 로그인 없이 이 글을 읽기 전용으로 볼 수 있습니다(검색 색인 제외, 댓글·공감 없음).
    만료되거나 폐기한 링크는 열 수 없고, 공개 목록·RSS·사이트맵에는 나타나지 않습니다.
</p>

<form method="post" action="/admin/posts/<?= (int) $post['id'] ?>/preview-links" class="row g-2 align-items-end mb-4">
    <?= csrf_field() ?>
    <div class="col-auto">
        <label class="form-label small mb-1" for="preview-days">유효 기간(일)</label>
        <input type="number" name="days" id="preview-days" class="form-control form-control-sm" value="<?= (int) $defaultDays ?>" min="1" max="<?= (int) $maxDays ?>" required>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm">링크 발급</button>
    </div>
</form>

<?php if ($links === []): ?>
    <p class="text-muted small">발급한 링크가 없습니다.</p>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-sm align-middle">
        <caption class="visually-hidden">미리보기 링크 목록</caption>
        <thead>
            <tr>
                <th scope="col">링크</th>
                <th scope="col">만료</th>
                <th scope="col">상태</th>
                <th scope="col"><span class="visually-hidden">관리</span></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($links as $link): ?>
            <?php
            $revoked = $link['revoked_at'] !== null;
            $expired = ! $revoked && strtotime((string) $link['expires_at']) <= time();
            $url     = base_url('preview/' . $link['token']);
            ?>
            <tr>
                <td>
                    <?php if ($revoked || $expired): ?>
                        <span class="text-muted">…<?= esc(substr((string) $link['token'], -8)) ?></span>
                    <?php else: ?>
                        <input type="text" id="preview-url-<?= (int) $link['id'] ?>" class="form-control form-control-sm" value="<?= esc($url, 'attr') ?>" readonly onfocus="this.select()" aria-label="미리보기 주소">
                    <?php endif; ?>
                </td>
                <td class="text-nowrap"><?= esc($link['expires_at']) ?></td>
                <td><?= $revoked ? '폐기됨' : ($expired ? '만료됨' : '사용 중') ?></td>
                <td class="text-end text-nowrap">
                    <?php if (! $revoked && ! $expired): ?>
                    <form method="post" action="/admin/posts/<?= (int) $post['id'] ?>/preview-links/<?= (int) $link['id'] ?>/revoke" class="d-inline"
                          onsubmit="return confirm('이 링크를 폐기할까요? 바로 열 수 없게 됩니다.')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-danger btn-sm">폐기</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
