<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '수정 이력 비교' ?>
<?= $this->section('content') ?>

<?php
// 한 필드의 줄 단위 차이를 그린다. 빨강 = 이 이력에만 있음, 초록 = 현재 글에만 있음
$renderDiff = static function (array $rows): void {
    if ($rows === []) {
        echo '<p class="text-muted small mb-0">(비어 있음)</p>';

        return;
    }
    echo '<pre class="border rounded p-2 small mb-0" style="white-space:pre-wrap">';
    foreach ($rows as $row) {
        $class  = ['same' => '', 'del' => 'bg-danger-subtle', 'add' => 'bg-success-subtle'][$row['type']];
        $prefix = ['same' => '  ', 'del' => '- ', 'add' => '+ '][$row['type']];
        echo '<span class="d-block ' . $class . '">' . esc($prefix . $row['text']) . '</span>';
    }
    echo '</pre>';
};
?>

<div class="mb-3 small">
    <a href="/admin/posts/<?= (int) $post['id'] ?>/revisions" class="text-decoration-none">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> 수정 이력 목록
    </a>
</div>

<p class="small text-muted">
    <?= esc($revision['created_at']) ?> 저장 직전의 버전과 현재 글을 비교합니다.
    <span class="badge text-bg-danger">-</span> 이 이력에만 있는 줄,
    <span class="badge text-bg-success">+</span> 현재 글에만 있는 줄.
</p>

<h2 class="h6 fw-semibold">제목</h2>
<div class="mb-3"><?php $renderDiff($titleDiff); ?></div>

<h2 class="h6 fw-semibold">메타 설명</h2>
<div class="mb-3"><?php $renderDiff($metaDiff); ?></div>

<h2 class="h6 fw-semibold">본문</h2>
<div class="mb-3"><?php $renderDiff($contentDiff); ?></div>

<form method="post" action="/admin/posts/<?= (int) $post['id'] ?>/revisions/<?= (int) $revision['id'] ?>/restore"
      onsubmit="return confirm('이 버전으로 복구할까요? 지금 글은 이력에 남습니다.')">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-primary btn-sm">이 버전으로 복구</button>
</form>

<?= $this->endSection() ?>
