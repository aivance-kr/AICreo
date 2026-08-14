<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '대시보드' ?>
<?= $this->section('content') ?>

<!-- 통계 카드 -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['label' => '총 게시글',  'value' => $stats['total_posts'],     'icon' => 'bi-card-text',  'color' => 'primary', 'href' => '/admin/posts'],
        ['label' => '총 회원',    'value' => $stats['total_users'],     'icon' => 'bi-people',     'color' => 'success', 'href' => '/admin/users'],
        ['label' => '전체 문의',  'value' => $stats['total_inquiries'], 'icon' => 'bi-envelope',   'color' => 'info',    'href' => '/admin/inquiries'],
        ['label' => '미읽음 문의','value' => $stats['unread_inquiries'],'icon' => 'bi-bell',       'color' => 'warning', 'href' => '/admin/inquiries?filter=unread'],
    ];
    foreach ($cards as $c):
    ?>
    <div class="col-sm-6 col-xl-3">
        <a href="<?= $c['href'] ?>" class="card border-0 shadow-sm text-decoration-none">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-<?= $c['color'] ?> bg-opacity-10 rounded p-3">
                    <i class="bi <?= $c['icon'] ?> fs-4 text-<?= $c['color'] ?>" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="text-muted small"><?= $c['label'] ?></div>
                    <div class="fs-4 fw-bold text-dark"><?= number_format($c['value']) ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <!-- 최근 문의 -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between">
                <strong>최근 문의</strong>
                <a href="/admin/inquiries" class="small text-decoration-none">전체보기</a>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($recentInquiries as $inq): ?>
                <a href="/admin/inquiries/<?= $inq['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <?php if (! $inq['is_read']): ?><span class="badge bg-danger me-1">NEW</span><?php endif; ?>
                        <span class="small"><?= esc($inq['name']) ?></span>
                        <span class="text-muted small ms-1"><?= esc($inq['subject'] ?: mb_substr($inq['message'], 0, 20)) ?></span>
                    </div>
                    <span class="text-muted small"><?= substr($inq['created_at'], 0, 10) ?></span>
                </a>
                <?php endforeach; ?>
                <?php if (empty($recentInquiries)): ?>
                    <div class="list-group-item text-muted small text-center py-3">문의가 없습니다</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 최근 게시글 -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between">
                <strong>최근 게시글</strong>
                <a href="/admin/posts" class="small text-decoration-none">전체보기</a>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($recentPosts as $post): ?>
                <a href="/board/<?= esc($post['board_slug']) ?>/<?= $post['id'] ?>"
                   target="_blank" rel="noopener"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div class="text-truncate" style="max-width:240px">
                        <span class="badge bg-light text-dark border me-1 small"><?= esc($post['board_name']) ?></span>
                        <span class="small text-dark"><?= esc($post['title']) ?></span>
                    </div>
                    <span class="text-muted small flex-shrink-0 ms-2"><?= substr($post['created_at'], 0, 10) ?></span>
                </a>
                <?php endforeach; ?>
                <?php if (empty($recentPosts)): ?>
                    <div class="list-group-item text-muted small text-center py-3">게시글이 없습니다</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
