<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '전체 댓글 관리' ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3 small">
    <div class="btn-group btn-group-sm" role="group" aria-label="댓글 필터">
        <a href="/admin/comments" class="btn btn-outline-secondary <?= $pendingOnly ? '' : 'active' ?>">전체</a>
        <a href="/admin/comments?status=pending" class="btn btn-outline-secondary <?= $pendingOnly ? 'active' : '' ?>">승인 대기 (<?= number_format($pendingCount) ?>)</a>
    </div>
    <span class="text-muted">총 <?= number_format($total) ?>건</span>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 table-stack">
            <thead class="table-light">
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">게시판</th>
                    <th scope="col">게시글</th>
                    <th scope="col">댓글</th>
                    <th scope="col">작성자</th>
                    <th scope="col">작성일</th>
                    <th scope="col">노출</th>
                    <th scope="col"><span class="visually-hidden">관리</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($comments)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">댓글이 없습니다.</td></tr>
                <?php endif; ?>
                <?php foreach ($comments as $comment): ?>
                <tr>
                    <td data-label="ID" class="text-muted small"><?= $comment['id'] ?></td>
                    <td data-label="게시판"><span class="badge bg-light text-dark border"><?= esc($comment['board_name']) ?></span></td>
                    <td data-label="게시글" class="text-truncate" style="max-width: 180px;">
                        <a href="/board/<?= esc($comment['board_slug']) ?>/<?= $comment['post_id'] ?>#comments" target="_blank" rel="noopener" class="text-decoration-none text-dark">
                            <?= esc($comment['post_title']) ?>
                        </a>
                    </td>
                    <td data-label="댓글" class="small text-truncate" style="max-width: 260px;"><?= esc(mb_strimwidth(trim(preg_replace('/\s+/', ' ', $comment['content']) ?? ''), 0, 80, '…')) ?></td>
                    <td data-label="작성자" class="small"><?= esc($comment['user_nickname'] ?? $comment['author_name']) ?></td>
                    <td data-label="작성일" class="small text-muted"><?= date('Y-m-d', strtotime($comment['created_at'])) ?></td>
                    <td data-label="노출">
                        <?php if ($comment['is_pending']): ?>
                        <span class="badge bg-warning text-dark">승인 대기</span>
                        <?php else: ?>
                        <span class="badge <?= $comment['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $comment['is_active'] ? '노출' : '숨김' ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td data-label="" class="cell-actions">
                        <?php if ($comment['is_pending']): ?>
                        <form method="post" action="/admin/comments/<?= $comment['id'] ?>/approve" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-success">승인</button>
                        </form>
                        <?php else: ?>
                        <form method="post" action="/admin/comments/<?= $comment['id'] ?>/toggle" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                <?= $comment['is_active'] ? '숨기기' : '노출' ?>
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="post" action="/admin/comments/<?= $comment['id'] ?>/delete" class="d-inline" onsubmit="return confirm('이 댓글을 휴지통으로 이동할까요?');">
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

<?php if ($totalPages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php
        // 현재 페이지가 속한 10개 단위 블록만 노출
        $blockSize = 10;
        $startPage = intdiv($currentPage - 1, $blockSize) * $blockSize + 1;
        $endPage   = min($startPage + $blockSize - 1, $totalPages);
        ?>
        <?php if ($startPage > 1): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?= $startPage - 1 ?>" aria-label="이전 페이지 묶음">&laquo;</a>
        </li>
        <?php endif; ?>
        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($endPage < $totalPages): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?= $endPage + 1 ?>" aria-label="다음 페이지 묶음">&raquo;</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?= $this->endSection() ?>
