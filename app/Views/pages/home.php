<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php if (($settings['show_home_search'] ?? '1') === '1'): ?>
<?= view('components/search_form', ['keyword' => '']) ?>
<?php endif; ?>

<!-- 메인 상단 배너 -->
<?= view('components/banner_slot', ['banners' => $mainTopBanners]) ?>
<!-- 홈 상단 광고 -->
<?= view('components/ad_slot', ['ads' => $homeTopAds]) ?>

<?php if ($page['is_custom_home'] ?? false): ?>
<!-- 관리자 페이지 관리에서 슬러그 home으로 저장한 홈 콘텐츠 -->
<section class="py-5">
    <div class="container">
        <?php if (trim((string) ($page['content'] ?? '')) === ''): ?>
            <h1 class="h2 fw-bold mb-4"><?= esc($page['title']) ?></h1>
        <?php else: ?>
            <h1 class="visually-hidden"><?= esc($page['title']) ?></h1>
        <?php endif; ?>
        <div class="page-content">
            <?= $page['content'] /* TinyMCE HTML 콘텐츠 */ ?>
        </div>
    </div>
</section>
<?php else: ?>
<?= view('pages/_aicreo_intro', ['settings' => $settings]) ?>
<?php endif; ?>

<?php if ($showLatestNotices ?? true): ?>
<!-- 최신 공지 -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 fw-bold mb-0"><?= esc(lang('Content.latestNotices')) ?></h2>
            <?php if (!empty($latestPosts)): ?>
            <a href="/board/notice" class="text-decoration-none small"><?= esc(lang('Content.viewAll')) ?> <i class="bi bi-chevron-right" aria-hidden="true"></i></a>
            <?php endif; ?>
        </div>
        <?php if (!empty($latestPosts)): ?>
        <div class="list-group">
            <?php foreach ($latestPosts as $post): ?>
            <a href="/board/notice/<?= $post['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <span><?= esc($post['title']) ?></span>
                <span class="text-muted small"><?= substr($post['created_at'], 0, 10) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="list-group">
            <div class="list-group-item text-muted small">
                <?= esc(lang('Content.noNotices')) ?>
                <?php if (($authUser['role'] ?? '') === 'admin'): ?>
                    <a href="/board/notice/write" class="text-decoration-none"><?= esc(lang('Content.writeFirstNotice')) ?> <i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- 홈 하단 광고 -->
<?= view('components/ad_slot', ['ads' => $homeBottomAds]) ?>
<!-- 메인 하단 배너 -->
<?= view('components/banner_slot', ['banners' => $mainBotBanners]) ?>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= asset_url('js/code-highlight.js') ?>" defer></script>
<?= $this->endSection() ?>
