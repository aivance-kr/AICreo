<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?= view('components/search_form', ['keyword' => '']) ?>

<?php
/** 홈 인피드 광고를 넣을 위치(목록 n번째 뒤) */
$feedAfter = [2, 5];
$postUrl   = static fn (array $p): string => '/board/' . $p['board_slug'] . '/' . $p['id'];
$author    = static fn (array $p): string => (string) ($p['user_nickname'] ?? mask_name($p['author_name'] ?? ''));
?>

<section class="qt-intro">
    <?php $introTitle = (string) ($page['title'] ?: ($settings['site_name'] ?? '')); ?>
    <h1<?= $introTitle === ($settings['site_name'] ?? '') ? ' class="visually-hidden"' : '' ?>><?= esc($introTitle) ?></h1>
    <?php if (! empty($settings['site_desc'])): ?><p><?= esc($settings['site_desc']) ?></p><?php endif; ?>
</section>

<?php if ($latestBlogPosts !== []): ?>
<div class="qt-list">
    <?php foreach ($latestBlogPosts as $i => $post): ?>
    <?php $thumb = \App\Models\PostModel::extractThumbnail($post['content']); ?>
    <article class="qt-item">
        <a href="<?= esc($postUrl($post)) ?>">
            <div class="qt-item__text">
                <span class="qt-meta"><b><?= esc($post['board_name']) ?></b> · <time datetime="<?= esc($post['created_at']) ?>"><?= esc(substr((string) $post['created_at'], 0, 10)) ?></time></span>
                <h2><?= esc($post['title']) ?></h2>
                <p><?= esc(post_excerpt($post['content'], 110)) ?></p>
                <span class="qt-meta"><?= post_read_minutes($post['content']) ?>분 읽기 · <?= esc($author($post)) ?></span>
            </div>
            <?php if ($thumb): ?><img class="qt-item__img" src="<?= esc($thumb, 'attr') ?>" alt="" loading="<?= $i < 2 ? 'eager' : 'lazy' ?>"><?php else: ?><div class="qt-item__img qt-item__img--blank" aria-hidden="true"></div><?php endif; ?>
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
    <p class="qt-empty">표시할 공개 글이 아직 없습니다.</p>
<?php endif; ?>

<?= view('components/ad_slot', ['ads' => $homeBottomAds, 'slot' => 'home_bottom']) ?>

<?= $this->endSection() ?>
