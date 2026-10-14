<?php if (!empty($ads)): ?>
<div class="container py-2 text-center ad-slot">
    <?php foreach ($ads as $ad): ?>
    <?= $ad['code'] /* 관리자가 등록한 광고 스니펫(구글 애드센스 등) — 그대로 출력 */ ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
