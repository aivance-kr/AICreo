<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php if (($settings['show_home_search'] ?? '1') === '1'): ?>
<?= view('components/search_form', ['keyword' => '']) ?>
<?php endif; ?>

<?php
/** 홈 인피드 광고를 넣을 위치(목록 n번째 뒤) */
$feedAfter = [2, 5];
$postUrl   = static fn (array $p): string => '/board/' . $p['board_slug'] . '/' . $p['id'];
$author    = static fn (array $p): string => (string) ($p['user_nickname'] ?? mask_name($p['author_name'] ?? ''));
?>

<section class="fm-intro">
    <?php $introTitle = (string) ($page['title'] ?: ($settings['site_name'] ?? '')); ?>
    <h1<?= $introTitle === ($settings['site_name'] ?? '') ? ' class="visually-hidden"' : '' ?>><?= esc($introTitle) ?></h1>
    <?php if (! empty($settings['site_desc'])): ?><p><?= esc($settings['site_desc']) ?></p><?php endif; ?>
</section>

<?php if ($latestBlogPosts !== []): ?>
<div class="fm-list">
    <?php foreach ($latestBlogPosts as $i => $post): ?>
    <?php $thumb = \App\Models\PostModel::extractThumbnail($post['content']); ?>
    <article class="fm-item">
        <a href="<?= esc($postUrl($post)) ?>">
            <?php if ($thumb): ?><img class="fm-item__img" src="<?= esc(content_thumb_url($thumb), 'attr') ?>" alt="" loading="<?= $i < 2 ? 'eager' : 'lazy' ?>"><?php else: ?><div class="fm-item__img fm-item__img--blank" aria-hidden="true"></div><?php endif; ?>
            <div class="fm-item__text">
                <span class="fm-meta"><b><?= esc($post['board_name']) ?></b> · <time datetime="<?= esc($post['created_at']) ?>"><?= esc(substr((string) $post['created_at'], 0, 10)) ?></time></span>
                <h2><?= esc($post['title']) ?></h2>
                <p><?= esc(post_excerpt($post['content'], 110)) ?></p>
                <span class="fm-meta"><?= esc(lang('Site.readMinutesRead', [(string) post_read_minutes($post['content'])])) ?> · <?= esc($author($post)) ?></span>
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
    <p class="fm-empty"><?= esc(lang('Site.noPublicPosts')) ?></p>
<?php endif; ?>

<?= view('components/ad_slot', ['ads' => $homeBottomAds, 'slot' => 'home_bottom']) ?>

<?= $this->endSection() ?>
