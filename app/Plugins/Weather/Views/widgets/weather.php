<?php /** @var list<array{region: string, weather: array<string, mixed>|null}> $items */ ?>
<?php if ($items !== []): ?>
<section class="card mb-3" aria-label="날씨">
    <ul class="list-group list-group-flush">
        <?php foreach ($items as $item): ?>
        <?php $w = $item['weather']; ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <?php if ($w === null): ?>
                <span><?= esc($item['region']) ?></span>
                <span class="text-muted small">정보 없음</span>
            <?php else: ?>
                <span><?= esc($w['name']) ?></span>
                <span><span aria-hidden="true"><?= $w['icon'] ?></span> <?= esc(number_format($w['temperature'], 1)) ?>°C <span class="text-muted small"><?= esc($w['label']) ?></span></span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>
