<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '대시보드' ?>
<?= $this->section('content') ?>

<!-- 빠른 작업 — 매일 반복하는 작성 액션을 사이드바 재탐색 없이 바로 시작한다 -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="/admin/pages/create" class="btn btn-primary btn-sm">
        <i class="bi bi-file-earmark-plus me-1" aria-hidden="true"></i>새 페이지
    </a>
    <a href="/admin/banners/create" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-image me-1" aria-hidden="true"></i>배너 등록
    </a>
    <a href="/admin/popups/create" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-window-plus me-1" aria-hidden="true"></i>팝업 등록
    </a>
</div>

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
        // 조치가 필요한 값(미읽음 문의 > 0)만 무게를 다르게 줘 참고용 총계와 구분한다.
        $needsAction = $c['label'] === '미읽음 문의' && $c['value'] > 0;
    ?>
    <div class="col-sm-6 col-xl-3">
        <a href="<?= $c['href'] ?>" class="card border-0 shadow-sm text-decoration-none<?= $needsAction ? ' bg-warning bg-opacity-10' : '' ?>">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-<?= $c['color'] ?> <?= $needsAction ? '' : 'bg-opacity-10' ?> rounded p-3">
                    <i class="bi <?= $c['icon'] ?> fs-4 <?= $needsAction ? 'text-white' : 'text-' . $c['color'] ?>" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="text-muted small">
                        <?= $c['label'] ?>
                        <?php if ($needsAction): ?><span class="fw-semibold text-warning-emphasis">· 확인 필요</span><?php endif; ?>
                    </div>
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
