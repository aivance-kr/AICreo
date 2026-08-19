<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '도움말' ?>
<?= $this->section('content') ?>

<div class="card border-0 shadow-sm">
    <div class="card-body manual-content">
        <?= $manualHtml /* CommonMark 로 변환된 HTML — 원본이 저장소에 커밋된 docs/manual.md 라 신뢰 가능 */ ?>
    </div>
</div>

<?= $this->endSection() ?>
