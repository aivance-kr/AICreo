<?= $this->extend('layouts/main') ?>

<?= $this->section('head') ?>
<?php $baseUrl = base_url('board/' . $board['slug']); ?>
<?php if ($currentPage > 1): ?>
<link rel="prev" href="<?= esc($baseUrl . ($currentPage - 1 > 1 ? '?page=' . ($currentPage - 1) : ''), 'attr') ?>">
<?php endif; ?>
<?php if ($currentPage < $totalPages): ?>
<link rel="next" href="<?= esc($baseUrl . '?page=' . ($currentPage + 1), 'attr') ?>">
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="container py-4">
<?php if (($settings['active_theme'] ?? 'default') !== 'blog' && ! empty($categories)): ?>
<div class="row g-4">
<div class="col-lg-3 order-lg-2">
    <nav class="list-group mb-4" aria-label="카테고리">
        <a href="/board/<?= esc($board['slug']) ?>" class="list-group-item list-group-item-action <?= $currentCategory === null ? 'active' : '' ?>">전체</a>
        <?php foreach ($categories as $cat): ?>
        <a href="/board/<?= esc($board['slug']) ?>?category=<?= esc($cat['id']) ?>" class="list-group-item list-group-item-action <?= $currentCategory === (int) $cat['id'] ? 'active' : '' ?>"><?= esc($cat['name']) ?></a>
        <?php endforeach; ?>
    </nav>
</div>
<div class="col-lg-9 order-lg-1">
<?php else: ?>
<div class="row g-4"><div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0"><?= esc($board['name']) ?></h1>
        <?php if ($board['description']): ?>
            <small class="text-muted"><?= esc($board['description']) ?></small>
        <?php endif; ?>
    </div>
    <a href="/board/<?= esc($board['slug']) ?>/write<?= $currentCategory ? '?category=' . $currentCategory : '' ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-pencil-square" aria-hidden="true"></i> 글쓰기
    </a>
</div>

<!-- 검색 -->
<form class="d-flex flex-wrap gap-2 mb-3" method="get" role="search">
    <?php if ($currentCategory): ?><input type="hidden" name="category" value="<?= $currentCategory ?>"><?php endif; ?>
    <label class="visually-hidden" for="search-type">검색 범위</label>
    <select name="type" id="search-type" class="form-select form-select-sm" style="width:120px">
        <option value="title"   <?= $searchType === 'title'   ? 'selected' : '' ?>>제목</option>
        <option value="content" <?= $searchType === 'content' ? 'selected' : '' ?>>내용</option>
        <option value="all"     <?= $searchType === 'all'     ? 'selected' : '' ?>>제목+내용</option>
    </select>
    <label class="visually-hidden" for="search-keyword">검색어</label>
    <input type="text" name="keyword" id="search-keyword" class="form-control form-control-sm flex-grow-1 w-auto" value="<?= esc($keyword ?? '') ?>" placeholder="검색어">
    <button class="btn btn-outline-secondary btn-sm" type="submit">
        <i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">검색</span>
    </button>
    <?php if ($keyword): ?>
        <a href="/board/<?= esc($board['slug']) ?>" class="btn btn-outline-danger btn-sm">초기화</a>
    <?php endif; ?>
</form>

<?php $listSkin = $board['list_skin'] ?? 'list'; ?>

<?php if ($listSkin === 'blog'): ?>
<!-- 블로그형 -->
<div class="board-list-blog list-group list-group-flush border-top">
    <?php foreach (array_merge($notices, $posts) as $post): ?>
    <?php $thumb = \App\Models\PostModel::extractThumbnail($post['content'] ?? null); ?>
    <a href="/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?>" class="list-group-item list-group-item-action py-4">
        <div class="d-flex gap-3">
            <?php if ($thumb): ?>
            <img src="<?= esc($thumb, 'attr') ?>" alt="" class="rounded flex-shrink-0" style="width:120px;height:90px;object-fit:cover">
            <?php endif; ?>
            <div class="flex-grow-1">
                <?php if ($post['is_notice']): ?><span class="badge text-bg-warning mb-1">공지</span><?php endif; ?>
                <?php if (! empty($post['category_name'])): ?><span class="badge text-bg-secondary mb-1"><?= esc($post['category_name']) ?></span><?php endif; ?>
                <h2 class="h6 mb-1 text-dark"><?= esc($post['title']) ?><?php if ($post['is_secret']): ?> <i class="bi bi-lock-fill text-muted small" aria-hidden="true"></i><?php endif; ?></h2>
                <p class="text-muted small mb-1"><?= esc(mb_substr(trim(strip_tags((string) $post['content'])), 0, 100)) ?></p>
                <div class="text-muted small">
                    <span><?= esc($post['user_nickname'] ?? mask_name($post['author_name'])) ?></span>
                    · <span><?= substr($post['created_at'], 0, 10) ?></span>
                    · <span>조회 <?= number_format($post['views']) ?></span>
                </div>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
    <?php if (empty($notices) && empty($posts)): ?>
    <p class="text-muted text-center py-5 mb-0">게시글이 없습니다.</p>
    <?php endif; ?>
</div>

<?php elseif ($listSkin === 'gallery'): ?>
<!-- 갤러리형 -->
<div class="board-list-gallery row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
    <?php foreach (array_merge($notices, $posts) as $post): ?>
    <?php $thumb = \App\Models\PostModel::extractThumbnail($post['content'] ?? null); ?>
    <div class="col">
        <a href="/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?>" class="text-decoration-none text-dark">
            <div class="ratio ratio-1x1 bg-light rounded mb-2 overflow-hidden">
                <?php if ($thumb): ?>
                <img src="<?= esc($thumb, 'attr') ?>" alt="" style="object-fit:cover">
                <?php else: ?>
                <div class="d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image" style="font-size:2rem" aria-hidden="true"></i></div>
                <?php endif; ?>
            </div>
            <div class="small text-truncate"><?php if ($post['is_notice']): ?><span class="badge text-bg-warning">공지</span> <?php endif; ?><?= esc($post['title']) ?></div>
        </a>
    </div>
    <?php endforeach; ?>
    <?php if (empty($notices) && empty($posts)): ?>
    <p class="text-muted text-center py-5 mb-0">게시글이 없습니다.</p>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- 일반 리스트형 -->
<div class="table-responsive">
<table class="table table-hover board-table table-stack">
    <caption class="visually-hidden"><?= esc($board['name']) ?> 게시글 목록</caption>
    <thead>
        <tr>
            <th scope="col" style="width:60px" class="text-center">번호</th>
            <th scope="col">제목</th>
            <th scope="col" style="width:100px" class="text-center">작성자</th>
            <th scope="col" style="width:90px"  class="text-center">날짜</th>
            <th scope="col" style="width:60px"  class="text-center">조회</th>
        </tr>
    </thead>
    <tbody>
        <!-- 공지 -->
        <?php foreach ($notices as $post): ?>
        <tr class="table-warning">
            <td class="text-center" data-label=""><span class="badge text-bg-warning">공지</span></td>
            <td data-label="제목">
                <a href="/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?>" class="text-decoration-none text-dark fw-semibold">
                    <?php if (! empty($post['category_name'])): ?><span class="badge text-bg-secondary"><?= esc($post['category_name']) ?></span> <?php endif; ?>
                    <?= esc($post['title']) ?>
                    <?php if ($post['is_secret']): ?> <i class="bi bi-lock-fill text-muted small" aria-hidden="true"></i><span class="visually-hidden">비밀글</span><?php endif; ?>
                </a>
            </td>
            <td class="text-center text-muted small" data-label="작성자"><?= esc(mask_name($post['author_name'])) ?></td>
            <td class="text-center text-muted small" data-label="날짜"><?= substr($post['created_at'], 0, 10) ?></td>
            <td class="text-center text-muted small" data-label="조회"><?= number_format($post['views']) ?></td>
        </tr>
        <?php endforeach; ?>

        <!-- 일반글 -->
        <?php if (empty($posts)): ?>
        <tr><td colspan="5" class="text-center py-5 text-muted">게시글이 없습니다.</td></tr>
        <?php endif; ?>
        <?php foreach ($posts as $i => $post): ?>
        <tr>
            <td class="text-center text-muted small" data-label="번호">
                <?= $total - (($currentPage - 1) * $board['posts_per_page']) - $i ?>
            </td>
            <td data-label="제목">
                <a href="/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?>" class="text-decoration-none text-dark">
                    <?php if (! empty($post['category_name'])): ?><span class="badge text-bg-secondary"><?= esc($post['category_name']) ?></span> <?php endif; ?>
                    <?= esc($post['title']) ?>
                    <?php if ($post['is_secret']): ?> <i class="bi bi-lock-fill text-muted small" aria-hidden="true"></i><span class="visually-hidden">비밀글</span><?php endif; ?>
                </a>
            </td>
            <td class="text-center text-muted small" data-label="작성자"><?= esc($post['user_nickname'] ?? mask_name($post['author_name'])) ?></td>
            <td class="text-center text-muted small" data-label="날짜"><?= substr($post['created_at'], 0, 10) ?></td>
            <td class="text-center text-muted small" data-label="조회"><?= number_format($post['views']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<!-- 페이지네이션 -->
<?php
$extraQs = ($keyword ? '&keyword=' . urlencode($keyword) . '&type=' . $searchType : '')
    . ($currentCategory ? '&category=' . $currentCategory : '');
?>
<?php if ($totalPages > 1): ?>
<nav class="d-flex justify-content-center mt-3" aria-label="페이지 목록">
    <ul class="pagination pagination-sm">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?><?= $extraQs ?>"
                   <?= $p === $currentPage ? 'aria-current="page"' : '' ?>><span class="visually-hidden">페이지 </span><?= $p ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

</div>
</div>
</div>

<?= $this->endSection() ?>
