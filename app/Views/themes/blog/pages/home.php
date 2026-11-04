<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php if (($settings['show_home_search'] ?? '1') === '1'): ?>
<?= view('components/search_form', ['keyword' => '']) ?>
<?php endif; ?>

<!-- 홈 상단 광고 -->
<?= view('components/ad_slot', ['ads' => $homeTopAds]) ?>

<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold mb-1"><?= esc(lang('Site.latestPosts')) ?></h1>
                <p class="text-muted mb-0"><?= esc(lang('Site.latestPostsDesc')) ?></p>
            </div>
        </div>

        <?php if ($latestBlogPosts !== []): ?>
            <div class="list-group list-group-flush border-top">
                <?php foreach ($latestBlogPosts as $post): ?>
                    <article class="list-group-item py-4">
                        <div class="d-flex justify-content-between gap-3">
                            <div>
                                <span class="badge text-bg-light mb-2"><?= esc($post['board_name']) ?></span>
                                <h2 class="fw-bold mb-2" style="font-size:var(--fs-list-title);line-height:1.45"><a class="text-decoration-none" href="/board/<?= esc($post['board_slug']) ?>/<?= esc($post['id']) ?>"><?= esc($post['title']) ?></a></h2>
                                <div class="post-content blog-post-body home-post-content mb-3"><?= $post['content'] ?></div>
                                <div class="blog-post-meta text-muted" style="font-size:var(--fs-meta)">
                                    <span><i class="bi bi-person" aria-hidden="true"></i><span class="visually-hidden"><?= esc(lang('Site.authorLabel')) ?> </span><?= esc($post['user_nickname'] ?? mask_name($post['author_name'] ?? '')) ?></span>
                                    <span><i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden"><?= esc(lang('Site.viewsLabel')) ?> </span><?= number_format((int) ($post['views'] ?? 0)) ?></span>
                                </div>
                            </div>
                            <time class="text-muted small text-nowrap" datetime="<?= esc($post['created_at']) ?>">
                                <?= esc(substr((string) $post['created_at'], 0, 10)) ?>
                            </time>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted"><?= esc(lang('Site.noPublicPosts')) ?></p>
        <?php endif; ?>
    </div>
</section>

<!-- 홈 하단 광고 -->
<?= view('components/ad_slot', ['ads' => $homeBottomAds]) ?>

<?= $this->endSection() ?>
