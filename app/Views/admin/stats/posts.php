<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '글별 조회 통계' ?>

<?= $this->section('content') ?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="<?= base_url('admin/stats') ?>" class="btn btn-sm btn-outline-secondary">접속 통계</a>
    <div class="btn-group btn-group-sm" role="group" aria-label="기간 선택">
        <?php foreach ($dayOptions as $option): ?>
        <a href="<?= base_url('admin/stats/posts') ?>?days=<?= $option ?>"
           class="btn btn-outline-primary <?= $days === $option ? 'active' : '' ?>"
           <?= $days === $option ? 'aria-current="true"' : '' ?>>최근 <?= $option ?>일</a>
        <?php endforeach; ?>
    </div>
    <span class="small text-muted">404 요청과 차단 중인 IP 의 방문은 제외합니다. 크롤러(User-Agent)는 기록 단계에서 이미 빠집니다. 10분 단위로 갱신됩니다.</span>
</div>

<div class="row g-3">
    <!-- 상위 글 -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><strong>조회 많은 글 (최근 <?= $days ?>일)</strong></div>
            <div class="list-group list-group-flush">
                <?php if (empty($topPosts)): ?>
                <div class="list-group-item text-muted small text-center py-3">글 조회 기록이 없습니다</div>
                <?php endif; ?>
                <?php foreach ($topPosts as $i => $post): ?>
                <?php $active = $selected !== null && $selected['id'] === $post['id']; ?>
                <a href="<?= base_url('admin/stats/posts') ?>?days=<?= $days ?>&post=<?= $post['id'] ?>"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $active ? 'active' : '' ?>"
                   <?= $active ? 'aria-current="true"' : '' ?>>
                    <span class="text-truncate small"><?= $i + 1 ?>. <?= esc($post['title']) ?></span>
                    <span class="badge bg-light text-dark border flex-shrink-0 ms-2"><?= number_format($post['count']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 선택한 글 추이 -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <?php if ($selected !== null): ?>
                <strong><?= esc($selected['title']) ?></strong>
                <span class="small text-muted">· <?= $weekly ? '주간' : '일간' ?> 조회 추이</span>
                <a href="<?= esc($selected['uri'], 'attr') ?>" target="_blank" rel="noopener" class="small ms-2">글 보기</a>
                <?php else: ?>
                <strong>조회 추이</strong>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($trend)): ?>
                <div class="text-muted small text-center py-4">선택할 글이 없습니다</div>
                <?php else: ?>
                <?php $max = max(1, max(array_column($trend, 'count'))); ?>
                <div class="d-flex align-items-end gap-1" style="height: 160px;">
                    <?php foreach ($trend as $bucket):
                        $label = $weekly ? 'W' . substr($bucket['key'], -2) : date('n/j', strtotime($bucket['key']));
                    ?>
                    <div class="d-flex flex-column align-items-center justify-content-end flex-grow-1 h-100"
                         title="<?= esc($bucket['key']) ?>: <?= number_format($bucket['count']) ?>건">
                        <span class="small text-muted mb-1"><?= $bucket['count'] > 0 && count($trend) <= 14 ? number_format($bucket['count']) : '' ?></span>
                        <div class="bg-primary bg-opacity-75 rounded-top w-100"
                             style="height: <?= (int) round($bucket['count'] / $max * 100) ?>%; min-height: 2px;"></div>
                        <span class="small text-muted mt-1" style="font-size: .65rem;"><?= count($trend) <= 14 || $bucket === $trend[0] ? $label : '' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($selected !== null): ?>
<div class="row g-3 mt-0">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>유입 경로</strong></div>
            <div class="list-group list-group-flush">
                <?php if (empty($referrers)): ?>
                <div class="list-group-item text-muted small text-center py-3">방문 기록이 없습니다</div>
                <?php endif; ?>
                <?php foreach ($referrers as $ref): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-truncate small text-dark"><?= esc($ref['host']) ?></span>
                    <span class="badge bg-light text-dark border flex-shrink-0 ms-2"><?= number_format($ref['count']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>유입 검색어</strong></div>
            <div class="list-group list-group-flush">
                <?php if (empty($keywords)): ?>
                <div class="list-group-item text-muted small text-center py-3">검색어 유입 기록이 없습니다</div>
                <?php endif; ?>
                <?php foreach ($keywords as $kw): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-truncate small text-dark"><?= esc($kw['keyword']) ?> <span class="text-muted">· <?= esc($kw['engine']) ?></span></span>
                    <span class="badge bg-light text-dark border flex-shrink-0 ms-2"><?= number_format($kw['count']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
