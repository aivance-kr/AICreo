<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '문의 상세' ?>
<?= $this->section('content') ?>

<div class="mb-2"><a href="/admin/inquiries" class="text-muted small"><i class="bi bi-arrow-left" aria-hidden="true"></i> 목록</a></div>

<div class="card border-0 shadow-sm" style="max-width:700px">
    <div class="card-body p-4">
        <table class="table table-borderless small mb-4">
            <tr><th scope="row" style="width:80px" class="text-muted">이름</th><td><?= esc($inquiry['name']) ?></td></tr>
            <tr><th scope="row" class="text-muted">이메일</th><td><a href="mailto:<?= esc($inquiry['email']) ?>"><?= esc($inquiry['email']) ?></a></td></tr>
            <?php if ($inquiry['phone']): ?>
            <tr><th scope="row" class="text-muted">연락처</th><td><?= esc($inquiry['phone']) ?></td></tr>
            <?php endif; ?>
            <?php if ($inquiry['subject']): ?>
            <tr><th scope="row" class="text-muted">제목</th><td><?= esc($inquiry['subject']) ?></td></tr>
            <?php endif; ?>
            <tr><th scope="row" class="text-muted">날짜</th><td><?= $inquiry['created_at'] ?></td></tr>
        </table>
        <div class="border rounded p-3 bg-light" style="white-space:pre-wrap"><?= esc($inquiry['message']) ?></div>
        <div class="mt-3 text-end">
            <a href="mailto:<?= esc($inquiry['email']) ?>?subject=<?= rawurlencode('Re: ' . ($inquiry['subject'] ?: $inquiry['name'] . '님 문의 답장')) ?>"
               class="btn btn-primary btn-sm">
                <i class="bi bi-reply me-1" aria-hidden="true"></i>이메일로 답장
            </a>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
