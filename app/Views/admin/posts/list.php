<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '전체 게시물 관리' ?>

<?= $this->section('content') ?>

<form method="get" class="row g-2 mb-3" role="search">
    <div class="col-auto">
        <label class="visually-hidden" for="post-board">게시판 필터</label>
        <select name="board_id" id="post-board" class="form-select form-select-sm">
            <option value="">전체 게시판</option>
            <?php foreach ($boards as $b): ?>
            <option value="<?= $b['id'] ?>" <?= $boardId === (int)$b['id'] ? 'selected' : '' ?>>
                <?= esc($b['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <label class="visually-hidden" for="post-search">제목 또는 작성자 검색</label>
        <input type="text" name="q" id="post-search" class="form-control form-control-sm" placeholder="제목 / 작성자 검색"
               value="<?= esc($keyword) ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-secondary btn-sm">검색</button>
        <a href="/admin/posts" class="btn btn-outline-secondary btn-sm">초기화</a>
    </div>
    <div class="col-auto ms-auto d-flex align-items-center text-muted small">
        총 <?= number_format($total) ?>건
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 table-stack">
            <thead class="table-light">
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">게시판</th>
                    <th scope="col">제목</th>
                    <th scope="col">작성자</th>
                    <th scope="col">조회</th>
                    <th scope="col">작성일</th>
                    <th scope="col"><span class="visually-hidden">관리</span></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($posts)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">게시물이 없습니다.</td></tr>
                <?php endif; ?>
                <?php foreach ($posts as $p): ?>
                <tr>
                    <td data-label="ID" class="text-muted small"><?= $p['id'] ?></td>
                    <td data-label="게시판"><span class="badge bg-light text-dark border"><?= esc($p['board_name']) ?></span></td>
                    <td data-label="제목">
                        <?php if ($p['is_notice']): ?>
                            <span class="badge bg-warning text-dark me-1">공지</span>
                        <?php endif; ?>
                        <?php if ($p['is_secret']): ?>
                            <span class="badge bg-secondary me-1">비밀</span>
                        <?php endif; ?>
                        <a href="/board/<?= esc($p['board_slug']) ?>/<?= $p['id'] ?>" target="_blank" rel="noopener"
                           class="text-decoration-none text-dark">
                            <?= esc($p['title']) ?>
                        </a>
                    </td>
                    <td data-label="작성자" class="small"><?= esc($p['user_nickname'] ?? $p['author_name']) ?></td>
                    <td data-label="조회" class="small text-muted"><?= number_format($p['views']) ?></td>
                    <td data-label="작성일" class="small text-muted"><?= date('Y-m-d', strtotime($p['created_at'])) ?></td>
                    <td data-label="" class="cell-actions">
                        <form method="post" action="/admin/posts/<?= $p['id'] ?>/delete" class="d-inline"
                              onsubmit="return confirm('정말 삭제하시겠습니까?')">
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
        $qs = http_build_query(array_filter(['q' => $keyword, 'board_id' => $boardId ?: '']));
        $qs = $qs ? '&' . $qs : '';
        ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?><?= $qs ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?= $this->endSection() ?>
