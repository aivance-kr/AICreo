<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
/** 홈 인피드 광고를 넣을 위치(목록 n번째 뒤) */
$feedAfter = [2, 5];
$postUrl   = static fn (array $p): string => '/board/' . $p['board_slug'] . '/' . $p['id'];
$author    = static fn (array $p): string => (string) ($p['user_nickname'] ?? mask_name($p['author_name'] ?? ''));
?>
<?= view('components/ad_slot', ['ads' => $homeTopAds, 'slot' => 'home_top']) ?>

<?php if ($latestBlogPosts !== []): ?>
    <?php $lead = $latestBlogPosts[0]; $leadThumb = \App\Models\PostModel::extractThumbnail($lead['content']); ?>
    <article class="il-lead">
        <a href="<?= esc($postUrl($lead)) ?>">
            <?php if ($leadThumb): ?><img class="il-lead__img" src="<?= esc($leadThumb, 'attr') ?>" alt="" loading="eager"><?php else: ?><div class="il-thumb" aria-hidden="true"></div><?php endif; ?>
            <span class="il-kicker"><?= esc($lead['board_name']) ?></span>
            <h2><?= esc($lead['title']) ?></h2>
            <p><?= esc(post_excerpt($lead['content'], 140)) ?></p>
            <span class="il-meta"><?= esc($author($lead)) ?> · <time datetime="<?= esc($lead['created_at']) ?>"><?= esc(substr((string) $lead['created_at'], 0, 10)) ?></time> · 읽는 시간 <?= post_read_minutes($lead['content']) ?>분</span>
        </a>
    </article>

    <div class="il-list">
        <?php foreach (array_slice($latestBlogPosts, 1) as $i => $post): ?>
        <?php $thumb = \App\Models\PostModel::extractThumbnail($post['content']); ?>
        <article class="il-row">
            <a href="<?= esc($postUrl($post)) ?>">
                <div>
                    <span class="il-kicker"><?= esc($post['board_name']) ?></span>
                    <h3><?= esc($post['title']) ?></h3>
                    <p><?= esc(post_excerpt($post['content'], 90)) ?></p>
                    <span class="il-meta"><?= esc($author($post)) ?> · <time datetime="<?= esc($post['created_at']) ?>"><?= esc(substr((string) $post['created_at'], 0, 10)) ?></time> · <?= post_read_minutes($post['content']) ?>분</span>
                </div>
                <?php if ($thumb): ?><img class="il-row__img" src="<?= esc($thumb, 'attr') ?>" alt="" loading="lazy"><?php else: ?><div class="il-thumb il-thumb--sm" aria-hidden="true"></div><?php endif; ?>
            </a>
        </article>
        <?php if (in_array($i + 1, $feedAfter, true)): ?>
            <?= view('components/ad_slot', ['ads' => $homeFeedAds, 'slot' => 'feed']) ?>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="il-empty">표시할 공개 글이 아직 없습니다.</p>
<?php endif; ?>

<?= view('components/ad_slot', ['ads' => $homeBottomAds, 'slot' => 'home_bottom']) ?>

<?= $this->endSection() ?>
