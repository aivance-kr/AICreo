<?php
/** @var string $keyword */
?>
<form class="container d-flex gap-2 py-3" action="<?= esc((string) parse_url(locale_url('search'), PHP_URL_PATH), 'attr') ?>" method="get" role="search">
    <label class="visually-hidden" for="site-search-q"><?= esc(lang('Site.searchLabel')) ?></label>
    <input type="search" name="q" id="site-search-q" class="form-control" value="<?= esc($keyword, 'attr') ?>" placeholder="<?= esc(lang('Site.searchLabel'), 'attr') ?>" maxlength="100">
    <button class="btn btn-outline-secondary" type="submit">
        <i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden"><?= esc(lang('Site.searchSubmit')) ?></span>
    </button>
</form>
