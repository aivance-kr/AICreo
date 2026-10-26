<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?= view('components/search_form', ['keyword' => '']) ?>

<?php
/** 홈 인피드 광고를 넣을 위치(목록 n번째 뒤) */
$feedAfter = [2, 5];
$postUrl   = static fn (array $p): string => '/board/' . $p['board_slug'] . '/' . $p['id'];
$author    = static fn (array $p): string => (string) ($p['user_nickname'] ?? mask_name($p['author_name'] ?? ''));
?>

<?php if ($latestBlogPosts !== []): ?>
<div class="cb-cards">
    <?php foreach ($latestBlogPosts as $i => $post): ?>
    <?php $thumb = \App\Models\PostModel::extractThumbnail($post['content']); ?>
    <article class="cb-card<?= $i === 0 ? ' cb-card--lead' : '' ?>">
        <a href="<?= esc($postUrl($post)) ?>">
            <?php if ($thumb): ?><img class="cb-card__img" src="<?= esc(content_thumb_url($thumb), 'attr') ?>" alt="" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>"><?php else: ?><div class="cb-card__img cb-card__img--blank cb-tone-<?= $i % 3 ?>" aria-hidden="true"><?= esc(mb_substr($post['title'], 0, 1)) ?></div><?php endif; ?>
            <div class="cb-card__body">
                <span class="cb-tag"><?= esc($post['board_name']) ?></span>
                <h2><?= esc($post['title']) ?></h2>
                <p><?= esc(post_excerpt($post['content'], $i === 0 ? 120 : 70)) ?></p>
                <span class="cb-meta"><time datetime="<?= esc($post['created_at']) ?>"><?= esc(substr((string) $post['created_at'], 0, 10)) ?></time> · <?= post_read_minutes($post['content']) ?>분</span>
            </div>
        </a>
    </article>
    <?php if ($i === 0): ?>
    <?= view('components/ad_slot', ['ads' => $homeTopAds, 'slot' => 'home_top']) ?>
    <?php endif; ?>
    <?php if (in_array($i + 1, $feedAfter, true)): ?>
    <?= view('components/ad_slot', ['ads' => $homeFeedAds, 'slot' => 'feed']) ?>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <p class="cb-empty">표시할 공개 글이 아직 없습니다.</p>
<?php endif; ?>

<?= view('components/ad_slot', ['ads' => $homeBottomAds, 'slot' => 'home_bottom']) ?>

<?= $this->endSection() ?>
