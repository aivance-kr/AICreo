<?php
/**
 * 번역 상자 — 언어별로 번역본 수정 링크 또는 "번역본 만들기" 버튼.
 *
 * @var list<array{locale:string,current:bool,edit:string|null,translate:string}> $translationRows
 */
if (count($translationRows ?? []) < 2) {
    return;
}
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white"><strong>번역</strong></div>
    <ul class="list-group list-group-flush">
        <?php foreach ($translationRows as $r): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center py-2">
            <span><span class="badge bg-light text-dark border"><?= esc(strtoupper($r['locale'])) ?></span>
                <?php if ($r['current']): ?><span class="small text-muted ms-1">현재 글</span><?php endif; ?>
            </span>
            <?php if ($r['edit'] !== null && ! $r['current']): ?>
                <a href="<?= esc($r['edit'], 'attr') ?>" class="btn btn-sm btn-outline-secondary">수정</a>
            <?php elseif ($r['edit'] === null): ?>
                <form method="post" action="<?= esc($r['translate'], 'attr') ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-primary">번역본 만들기</button>
                </form>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
