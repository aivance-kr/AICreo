<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?= view('components/search_form', ['keyword' => '']) ?>

<?php
/** 홈 인피드 광고를 넣을 위치(목록 n번째 뒤) */
$feedAfter = [2, 5];
$postUrl   = static fn (array $p): string => '/board/' . $p['board_slug'] . '/' . $p['id'];
$author    = static fn (array $p): string => (string) ($p['user_nickname'] ?? mask_name($p['author_name'] ?? ''));
?>

<?php $pageTitle = (string) ($page['title'] ?: '최근 글'); ?>
<h1 class="dn-page-title<?= $pageTitle === ($settings['site_name'] ?? '') ? ' visually-hidden' : '' ?>"><?= esc($pageTitle) ?></h1>

<?php if ($latestBlogPosts !== []): ?>
<div class="dn-list">
    <?php foreach ($latestBlogPosts as $i => $post): ?>
    <article class="dn-post">
        <a href="<?= esc($postUrl($post)) ?>">
            <span class="dn-tags"><span class="dn-tag dn-tag--key"><?= esc($post['board_name']) ?></span></span>
            <h2><?= esc($post['title']) ?></h2>
            <p><?= esc(post_excerpt($post['content'], 120)) ?></p>
            <span class="dn-meta"><time datetime="<?= esc($post['created_at']) ?>"><?= esc(substr((string) $post['created_at'], 0, 10)) ?></time> · <?= post_read_minutes($post['content']) ?> min</span>
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
    <p class="dn-empty">표시할 공개 글이 아직 없습니다.</p>
<?php endif; ?>

<?= view('components/ad_slot', ['ads' => $homeBottomAds, 'slot' => 'home_bottom']) ?>

<?= $this->endSection() ?>
