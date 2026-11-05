<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container py-4">
    <h1 class="fw-bold mb-3">날씨</h1>

    <?php if ($items === []): ?>
        <p class="text-muted">관리자 &gt; 플러그인 &gt; 날씨 위젯 &gt; 설정에서 지역을 입력하세요.</p>
    <?php endif; ?>

    <div class="row g-3">
        <?php foreach ($items as $item): ?>
        <?php $w = $item['weather']; ?>
        <div class="col-sm-6 col-lg-4">
            <div class="card h-100 text-center">
                <div class="card-body">
                    <?php if ($w === null): ?>
                        <h2 class="h6"><?= esc($item['region']) ?></h2>
                        <p class="text-muted small mb-0">날씨 정보를 불러오지 못했습니다.</p>
                    <?php else: ?>
                        <h2 class="h6"><?= esc($w['name']) ?></h2>
                        <div class="display-4" aria-hidden="true"><?= $w['icon'] ?></div>
                        <div class="fs-2 fw-bold"><?= esc(number_format($w['temperature'], 1)) ?>°C</div>
                        <div><?= esc($w['label']) ?></div>
                        <div class="text-muted small mt-2">습도 <?= esc((string) $w['humidity']) ?>% · 바람 <?= esc(number_format($w['wind'], 1)) ?>km/h</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?= $this->endSection() ?>
