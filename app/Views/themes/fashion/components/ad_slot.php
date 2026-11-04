<?php
// 게시글 본문 상·하단 등 기존 호출부(components/ad_slot)를 이 테마의 광고 슬롯 마크업으로 연결한다.
?>
<?= view('themes/default/components/ad_unit', ['ads' => $ads ?? [], 'slot' => $slot ?? 'post']) ?>
