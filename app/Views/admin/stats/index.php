<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '접속 통계' ?>

<?= $this->section('content') ?>

<!-- 통계 카드 -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['label' => '오늘 방문',     'value' => $todayCount,     'icon' => 'bi-eye',         'color' => 'primary'],
        ['label' => '오늘 순방문',   'value' => $todayUniqueIps, 'icon' => 'bi-person-check','color' => 'success'],
        ['label' => '최근 7일 방문', 'value' => $weekCount,      'icon' => 'bi-calendar-week', 'color' => 'info'],
        ['label' => '최근 30일 방문','value' => $monthCount,     'icon' => 'bi-calendar-month', 'color' => 'warning'],
    ];
    foreach ($cards as $c):
    ?>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-<?= $c['color'] ?> bg-opacity-10 rounded p-3">
                    <i class="bi <?= $c['icon'] ?> fs-4 text-<?= $c['color'] ?>" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="text-muted small"><?= $c['label'] ?></div>
                    <div class="fs-4 fw-bold text-dark"><?= number_format($c['value']) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <!-- 최근 14일 방문 추이 -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><strong>최근 14일 방문 추이</strong></div>
            <div class="card-body">
                <?php $max = max(1, max(array_column($dailyCounts, 'count'))); ?>
                <div class="d-flex align-items-end gap-2" style="height: 160px;">
                    <?php foreach ($dailyCounts as $day): ?>
                    <div class="d-flex flex-column align-items-center justify-content-end flex-grow-1 h-100">
                        <span class="small text-muted mb-1"><?= $day['count'] > 0 ? number_format($day['count']) : '' ?></span>
                        <div class="bg-primary bg-opacity-75 rounded-top w-100"
                             style="height: <?= (int) round($day['count'] / $max * 100) ?>%; min-height: 2px;"
                             title="<?= esc($day['date']) ?>: <?= number_format($day['count']) ?>건"></div>
                        <span class="small text-muted mt-1"><?= date('n/j', strtotime($day['date'])) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 인기 페이지 -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><strong>인기 페이지 (최근 30일)</strong></div>
            <div class="list-group list-group-flush">
                <?php if (empty($topPages)): ?>
                <div class="list-group-item text-muted small text-center py-3">방문 기록이 없습니다</div>
                <?php endif; ?>
                <?php foreach ($topPages as $page): ?>
                <a href="<?= esc($page['uri']) ?>" target="_blank" rel="noopener"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <span class="text-truncate small text-dark" style="max-width: 220px;"><?= esc($page['uri']) ?></span>
                    <span class="badge bg-light text-dark border flex-shrink-0 ms-2"><?= number_format($page['count']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
