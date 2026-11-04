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
<?php if (! \App\Libraries\ThemeView::isBlogTheme($settings['active_theme'] ?? 'default') && ! empty($categories)): ?>
<div class="row g-4">
<div class="col-lg-3 order-lg-2">
    <nav class="list-group mb-4" aria-label="<?= esc(lang('Board.categoryLabel'), 'attr') ?>">
        <a href="/board/<?= esc($board['slug']) ?>" class="list-group-item list-group-item-action <?= $currentCategory === null ? 'active' : '' ?>"><?= esc(lang('Board.all')) ?></a>
        <?php foreach ($categories as $cat): ?>
        <a href="/board/<?= esc($board['slug']) ?>?category=<?= esc($cat['id']) ?>" class="list-group-item list-group-item-action <?= $currentCategory === (int) $cat['id'] ? 'active' : '' ?>"><?= esc($cat['name']) ?></a>
        <?php endforeach; ?>
    </nav>
</div>
<div class="col-lg-9 order-lg-1">
<?php else: ?>
<div class="row g-4"><div>
<?php if (! empty($categories)): ?>
<nav class="nav nav-pills flex-wrap gap-1 mb-3" aria-label="<?= esc(lang('Board.categoryLabel'), 'attr') ?>">
    <a href="/board/<?= esc($board['slug']) ?>" class="nav-link py-1 px-3 <?= $currentCategory === null ? 'active' : '' ?>"<?= $currentCategory === null ? ' aria-current="page"' : '' ?>><?= esc(lang('Board.all')) ?></a>
    <?php foreach ($categories as $cat): ?>
    <a href="/board/<?= esc($board['slug']) ?>?category=<?= esc($cat['id']) ?>" class="nav-link py-1 px-3 <?= $currentCategory === (int) $cat['id'] ? 'active' : '' ?>"<?= $currentCategory === (int) $cat['id'] ? ' aria-current="page"' : '' ?>><?= esc($cat['name']) ?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0"><?= esc($board['name']) ?></h1>
        <?php if ($board['description']): ?>
            <small class="text-muted"><?= esc($board['description']) ?></small>
        <?php endif; ?>
    </div>
    <a href="/board/<?= esc($board['slug']) ?>/write<?= $currentCategory ? '?category=' . $currentCategory : '' ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-pencil-square" aria-hidden="true"></i> <?= esc(lang('Board.writePost')) ?>
    </a>
</div>

<!-- 검색 -->
<form class="d-flex flex-wrap gap-2 mb-3" method="get" role="search">
    <?php if ($currentCategory): ?><input type="hidden" name="category" value="<?= $currentCategory ?>"><?php endif; ?>
    <label class="visually-hidden" for="search-type"><?= esc(lang('Board.searchScope')) ?></label>
    <select name="type" id="search-type" class="form-select form-select-sm" style="width:120px">
        <option value="title"   <?= $searchType === 'title'   ? 'selected' : '' ?>><?= esc(lang('Board.title')) ?></option>
        <option value="content" <?= $searchType === 'content' ? 'selected' : '' ?>><?= esc(lang('Board.content')) ?></option>
        <option value="all"     <?= $searchType === 'all'     ? 'selected' : '' ?>><?= esc(lang('Board.titleContent')) ?></option>
        <option value="tag"     <?= $searchType === 'tag'     ? 'selected' : '' ?>><?= esc(lang('Board.tag')) ?></option>
    </select>
    <label class="visually-hidden" for="search-keyword"><?= esc(lang('Board.keyword')) ?></label>
    <input type="text" name="keyword" id="search-keyword" class="form-control form-control-sm flex-grow-1 w-auto" value="<?= esc($keyword ?? '') ?>" placeholder="<?= esc(lang('Board.keyword'), 'attr') ?>">
    <button class="btn btn-outline-secondary btn-sm" type="submit">
        <i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden"><?= esc(lang('Board.search')) ?></span>
    </button>
    <?php if ($keyword): ?>
        <a href="/board/<?= esc($board['slug']) ?>" class="btn btn-outline-danger btn-sm"><?= esc(lang('Board.reset')) ?></a>
    <?php endif; ?>
</form>

<?php $listSkin = $board['list_skin'] ?? 'list'; ?>

<?php if ($listSkin === 'blog'): ?>
<!-- 블로그형 -->
<div class="board-list-blog list-group list-group-flush border-top">
    <?php foreach (array_merge($notices, $posts) as $post): ?>
    <?php $thumb = \App\Models\PostModel::extractThumbnail($post['content'] ?? null); ?>
    <a href="/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?><?= esc($listQs, 'attr') ?>" class="list-group-item list-group-item-action py-4">
        <div class="d-flex gap-3">
            <?php if ($thumb): ?>
            <img src="<?= esc(content_thumb_url($thumb), 'attr') ?>" alt="" class="rounded flex-shrink-0" style="width:120px;height:90px;object-fit:cover">
            <?php endif; ?>
            <div class="flex-grow-1">
                <?php if ($post['is_notice']): ?><span class="badge text-bg-warning mb-1"><?= esc(lang('Board.notice')) ?></span><?php endif; ?>
                <?php if (! empty($post['category_name'])): ?><span class="badge text-bg-secondary mb-1"><?= esc($post['category_name']) ?></span><?php endif; ?>
                <h2 class="h6 mb-1 text-dark"><?= esc($post['title']) ?><?php if ($post['is_secret']): ?> <i class="bi bi-lock-fill text-muted small" aria-hidden="true"></i><?php endif; ?></h2>
                <p class="text-muted small mb-1"><?= esc(post_excerpt($post['content'], 100)) ?></p>
                <div class="text-muted small">
                    <span><?= esc($post['user_nickname'] ?? mask_name($post['author_name'])) ?></span>
                    · <span><?= substr($post['created_at'], 0, 10) ?></span>
                    · <span><?= esc(lang('Board.views')) ?> <?= number_format($post['views']) ?></span>
                    · <span><?= esc(lang('Board.likes')) ?> <?= number_format((int) $post['like_count']) ?></span>
                    · <span><?= esc(lang('Board.comments')) ?> <?= number_format((int) $post['comment_count']) ?></span>
                </div>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
    <?php if (empty($notices) && empty($posts)): ?>
    <p class="text-muted text-center py-5 mb-0"><?= esc(lang('Board.noPosts')) ?></p>
    <?php endif; ?>
</div>

<?php elseif ($listSkin === 'gallery'): ?>
<!-- 갤러리형 -->
<div class="board-list-gallery row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
    <?php foreach (array_merge($notices, $posts) as $post): ?>
    <?php $thumb = \App\Models\PostModel::extractThumbnail($post['content'] ?? null); ?>
    <div class="col">
        <a href="/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?><?= esc($listQs, 'attr') ?>" class="text-decoration-none text-dark">
            <div class="ratio ratio-1x1 bg-light rounded mb-2 overflow-hidden">
                <?php if ($thumb): ?>
                <img src="<?= esc(content_thumb_url($thumb), 'attr') ?>" alt="" style="object-fit:cover">
                <?php else: ?>
                <div class="d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image" style="font-size:2rem" aria-hidden="true"></i></div>
                <?php endif; ?>
            </div>
            <div class="small text-truncate"><?php if ($post['is_notice']): ?><span class="badge text-bg-warning"><?= esc(lang('Board.notice')) ?></span> <?php endif; ?><?= esc($post['title']) ?></div>
            <div class="text-muted small"><i class="bi bi-heart" aria-hidden="true"></i> <span class="visually-hidden"><?= esc(lang('Board.likes')) ?></span> <?= number_format((int) $post['like_count']) ?> · <i class="bi bi-chat" aria-hidden="true"></i> <span class="visually-hidden"><?= esc(lang('Board.comments')) ?></span> <?= number_format((int) $post['comment_count']) ?></div>
        </a>
    </div>
    <?php endforeach; ?>
    <?php if (empty($notices) && empty($posts)): ?>
    <p class="text-muted text-center py-5 mb-0"><?= esc(lang('Board.noPosts')) ?></p>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- 일반 리스트형 -->
<div class="table-responsive">
<table class="table table-hover board-table table-stack">
    <caption class="visually-hidden"><?= esc(lang('Board.listCaption', [$board['name']])) ?></caption>
    <thead>
        <tr>
            <th scope="col" style="width:60px" class="text-center"><?= esc(lang('Board.no')) ?></th>
            <th scope="col"><?= esc(lang('Board.title')) ?></th>
            <th scope="col" style="width:100px" class="text-center"><?= esc(lang('Board.author')) ?></th>
            <th scope="col" style="width:90px"  class="text-center"><?= esc(lang('Board.date')) ?></th>
            <th scope="col" style="width:60px"  class="text-center"><?= esc(lang('Board.views')) ?></th>
            <th scope="col" style="width:60px"  class="text-center"><?= esc(lang('Board.likes')) ?></th>
            <th scope="col" style="width:60px"  class="text-center"><?= esc(lang('Board.comments')) ?></th>
        </tr>
    </thead>
    <tbody>
        <!-- 공지 -->
        <?php foreach ($notices as $post): ?>
        <tr class="table-warning">
            <td class="text-center" data-label=""><span class="badge text-bg-warning"><?= esc(lang('Board.notice')) ?></span></td>
            <td data-label="<?= esc(lang('Board.title'), 'attr') ?>">
                <a href="/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?><?= esc($listQs, 'attr') ?>" class="text-decoration-none text-dark fw-semibold">
                    <?php if (! empty($post['category_name'])): ?><span class="badge text-bg-secondary"><?= esc($post['category_name']) ?></span> <?php endif; ?>
                    <?= esc($post['title']) ?>
                    <?php if ($post['is_secret']): ?> <i class="bi bi-lock-fill text-muted small" aria-hidden="true"></i><span class="visually-hidden"><?= esc(lang('Board.secret')) ?></span><?php endif; ?>
                </a>
            </td>
            <td class="text-center text-muted small" data-label="<?= esc(lang('Board.author'), 'attr') ?>"><?= esc(mask_name($post['author_name'])) ?></td>
            <td class="text-center text-muted small" data-label="<?= esc(lang('Board.date'), 'attr') ?>"><?= substr($post['created_at'], 0, 10) ?></td>
            <td class="text-center text-muted small" data-label="<?= esc(lang('Board.views'), 'attr') ?>"><?= number_format($post['views']) ?></td>
            <td class="text-center text-muted small" data-label="<?= esc(lang('Board.likes'), 'attr') ?>"><?= number_format((int) $post['like_count']) ?></td>
            <td class="text-center text-muted small" data-label="<?= esc(lang('Board.comments'), 'attr') ?>"><?= number_format((int) $post['comment_count']) ?></td>
        </tr>
        <?php endforeach; ?>

        <!-- 일반글 -->
        <?php if (empty($posts)): ?>
        <tr><td colspan="7" class="text-center py-5 text-muted"><?= esc(lang('Board.noPosts')) ?></td></tr>
        <?php endif; ?>
        <?php foreach ($posts as $i => $post): ?>
        <tr>
            <td class="text-center text-muted small" data-label="<?= esc(lang('Board.no'), 'attr') ?>">
                <?= $total - (($currentPage - 1) * $board['posts_per_page']) - $i ?>
            </td>
            <td data-label="<?= esc(lang('Board.title'), 'attr') ?>">
                <a href="/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?><?= esc($listQs, 'attr') ?>" class="text-decoration-none text-dark">
                    <?php if (! empty($post['category_name'])): ?><span class="badge text-bg-secondary"><?= esc($post['category_name']) ?></span> <?php endif; ?>
                    <?= esc($post['title']) ?>
                    <?php if ($post['is_secret']): ?> <i class="bi bi-lock-fill text-muted small" aria-hidden="true"></i><span class="visually-hidden"><?= esc(lang('Board.secret')) ?></span><?php endif; ?>
                </a>
            </td>
            <td class="text-center text-muted small" data-label="<?= esc(lang('Board.author'), 'attr') ?>"><?= esc($post['user_nickname'] ?? mask_name($post['author_name'])) ?></td>
            <td class="text-center text-muted small" data-label="<?= esc(lang('Board.date'), 'attr') ?>"><?= substr($post['created_at'], 0, 10) ?></td>
            <td class="text-center text-muted small" data-label="<?= esc(lang('Board.views'), 'attr') ?>"><?= number_format($post['views']) ?></td>
            <td class="text-center text-muted small" data-label="<?= esc(lang('Board.likes'), 'attr') ?>"><?= number_format((int) $post['like_count']) ?></td>
            <td class="text-center text-muted small" data-label="<?= esc(lang('Board.comments'), 'attr') ?>"><?= number_format((int) $post['comment_count']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<!-- 페이지네이션 -->
<?php
$extraQs = ($keyword ? '&keyword=' . urlencode($keyword) . '&type=' . urlencode((string) $searchType) : '')
    . ($currentCategory ? '&category=' . $currentCategory : '');
?>
<?php if ($totalPages > 1): ?>
<nav class="d-flex justify-content-center mt-3" aria-label="<?= esc(lang('Board.pageList'), 'attr') ?>">
    <ul class="pagination pagination-sm">
        <?php
        // 현재 페이지가 속한 10개 단위 블록만 노출
        $blockSize = 10;
        $startPage = intdiv($currentPage - 1, $blockSize) * $blockSize + 1;
        $endPage   = min($startPage + $blockSize - 1, $totalPages);
        ?>
        <?php if ($startPage > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?= $startPage - 1 ?><?= esc($extraQs, 'attr') ?>" aria-label="<?= esc(lang('Board.prevBlock'), 'attr') ?>">&laquo;</a>
            </li>
        <?php endif; ?>
        <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?><?= esc($extraQs, 'attr') ?>"
                   <?= $p === $currentPage ? 'aria-current="page"' : '' ?>><span class="visually-hidden"><?= esc(lang('Board.page')) ?> </span><?= $p ?></a>
            </li>
        <?php endfor; ?>
        <?php if ($endPage < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?= $endPage + 1 ?><?= esc($extraQs, 'attr') ?>" aria-label="<?= esc(lang('Board.nextBlock'), 'attr') ?>">&raquo;</a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

</div>
</div>
</div>

<?= $this->endSection() ?>
