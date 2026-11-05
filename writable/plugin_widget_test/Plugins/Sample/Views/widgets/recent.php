<?php if ($notes !== []): ?>
<section class="side-cats" aria-label="샘플 메모">
    <h2 class="side-cats__title">샘플 메모</h2>
    <ul class="side-cats__list">
        <?php foreach ($notes as $note): ?>
        <li><?= esc($note['body']) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>
