<?php
/**
 * 블로그형 테마 공용 광고 슬롯. 등록된 광고가 없으면 아무것도 그리지 않는다.
 *
 * - "광고" 라벨을 항상 표시한다(애드센스 정책: 광고와 콘텐츠를 구분).
 * - 슬롯별 최소 높이를 CSS 가 예약해, 광고가 늦게 떠도 본문이 밀리지 않는다(CLS).
 *
 * @var list<array<string, mixed>> $ads
 * @var string $slot  home_top | feed | home_bottom | post | sidebar
 */
if (empty($ads)) {
    return;
}
?>
<aside class="ad-unit" data-slot="<?= esc($slot ?? 'home_top', 'attr') ?>" aria-label="광고">
    <span class="ad-unit__label">광고</span>
    <?php foreach ($ads as $ad): ?>
    <?= $ad['code'] /* 관리자가 등록한 광고 스니펫(구글 애드센스 등) — 그대로 출력 */ ?>
    <?php endforeach; ?>
</aside>
