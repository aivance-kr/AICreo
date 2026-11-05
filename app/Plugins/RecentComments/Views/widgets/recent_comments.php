<?php /** @var list<array{author: string, excerpt: string, title: string, url: string}> $items */ ?>
<?php if ($items !== []): ?>
<section class="card mb-3" aria-label="최신 댓글">
    <div class="card-header">최신 댓글</div>
    <ul class="list-group list-group-flush">
        <?php foreach ($items as $item): ?>
        <li class="list-group-item">
            <a class="text-decoration-none" href="<?= esc($item['url'], 'attr') ?>">
                <span class="d-block"><?= esc($item['excerpt']) ?></span>
                <span class="d-block text-muted small"><?= esc($item['author']) ?> · <?= esc($item['title']) ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>
