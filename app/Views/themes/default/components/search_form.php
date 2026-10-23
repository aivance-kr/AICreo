<?php
/** @var string $keyword */
?>
<form class="container d-flex gap-2 py-3" action="/search" method="get" role="search">
    <label class="visually-hidden" for="site-search-q">글 검색</label>
    <input type="search" name="q" id="site-search-q" class="form-control" value="<?= esc($keyword, 'attr') ?>" placeholder="글 검색" maxlength="100">
    <button class="btn btn-outline-secondary" type="submit">
        <i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">검색</span>
    </button>
</form>
