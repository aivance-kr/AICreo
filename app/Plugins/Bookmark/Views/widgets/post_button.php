<?php if ($show): ?>
<form id="post-bookmark" method="post" action="/my/bookmarks/toggle/<?= (int) $postId ?>" class="text-center mb-4">
    <?= csrf_field() ?>
    <button type="submit" class="btn <?= $bookmarked ? 'btn-primary' : 'btn-outline-primary' ?>" aria-pressed="<?= $bookmarked ? 'true' : 'false' ?>">
        <i class="bi <?= $bookmarked ? 'bi-bookmark-heart-fill' : 'bi-bookmark-heart' ?>" aria-hidden="true"></i>
        <?= $bookmarked ? '북마크됨' : '북마크' ?>
    </button>
</form>
<?php endif; ?>
