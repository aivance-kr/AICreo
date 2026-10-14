<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '접속 통계' ?>

<?= $this->section('content') ?>

<!-- 기간 검색 -->
<form method="get" action="<?= base_url('admin/stats') ?>" class="card border-0 shadow-sm mb-3" id="statsSearch">
    <div class="card-body d-flex flex-wrap align-items-end gap-2">
        <div>
            <label for="statsPeriod" class="form-label small mb-1">기간 단위</label>
            <select name="period" id="statsPeriod" class="form-select form-select-sm">
                <?php foreach (['day' => '날짜', 'week' => '주별', 'month' => '월별', 'year' => '연도별'] as $k => $label): ?>
                <option value="<?= $k ?>" <?= $period === $k ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="statsValue" class="form-label small mb-1">기간</label>
            <input type="text" name="value" id="statsValue" class="form-control form-control-sm"
                   value="<?= esc($value, 'attr') ?>" required>
        </div>
        <button type="submit" class="btn btn-sm btn-primary">검색</button>
        <?php if ($period !== ''): ?>
        <a href="<?= base_url('admin/stats') ?>" class="btn btn-sm btn-outline-secondary">초기화</a>
        <?php endif; ?>
    </div>
</form>
<script>
(function () {
    var period = document.getElementById('statsPeriod');
    var value = document.getElementById('statsValue');
    var types = {day: 'date', week: 'week', month: 'month', year: 'number'};
    function sync(keep) {
        var v = keep ? value.value : '';
        value.type = types[period.value];
        if (period.value === 'year') { value.min = 2000; value.max = 2100; value.placeholder = '2026'; }
        value.value = v;
    }
    period.addEventListener('change', function () { sync(false); });
    sync(true);
})();
</script>

<!-- 통계 카드 -->
<div class="row g-3 mb-4">
    <?php
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
            <div class="card-header bg-white"><strong><?= esc($trendTitle) ?></strong></div>
            <div class="card-body">
                <?php $max = max(1, max(array_column($trend, 'count'))); ?>
                <div class="d-flex align-items-end gap-2" style="height: 160px;">
                    <?php foreach ($trend as $day):
                        $href = $monthly
                            ? base_url('admin/stats') . '?period=month&value=' . $day['key']
                            : base_url('admin/stats/day/' . $day['key']);
                        $label = $monthly ? (int) substr($day['key'], 5, 2) . '월' : date('n/j', strtotime($day['key']));
                    ?>
                    <a href="<?= esc($href, 'attr') ?>" class="d-flex flex-column align-items-center justify-content-end flex-grow-1 h-100 text-decoration-none"
                       title="<?= esc($day['key']) ?>: <?= number_format($day['count']) ?>건">
                        <span class="small text-muted mb-1"><?= $day['count'] > 0 ? number_format($day['count']) : '' ?></span>
                        <div class="bg-primary bg-opacity-75 rounded-top w-100"
                             style="height: <?= (int) round($day['count'] / $max * 100) ?>%; min-height: 2px;"></div>
                        <span class="small text-muted mt-1"><?= $label ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 인기 페이지 -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><strong><?= esc($topTitle) ?></strong></div>
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

<!-- 유입 경로 -->
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white"><strong>유입 경로</strong> <span class="small text-muted">(어디서 들어왔는지)</span></div>
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

<?= $this->endSection() ?>
