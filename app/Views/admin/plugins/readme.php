<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('pageTitle', $plugin['name'] . ' 설명') ?>
<?= $this->section('content') ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body manual-content">
        <?= $readmeHtml /* MarkdownRenderer 변환 결과 — 원문 HTML 은 제거되고 위험한 링크는 막힌다 */ ?>
    </div>
</div>
<a href="/admin/plugins" class="btn btn-outline-secondary">목록</a>

<?= $this->endSection() ?>
