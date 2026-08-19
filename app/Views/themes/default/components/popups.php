<?php if (empty($activePopups)) return; ?>

<?php
/** 실제 파일에서 가로/세로를 읽어 <img> 에 박아 로드 전후 레이아웃 밀림(CLS)을 막는다.
 *  실패해도 렌더링을 막지 않도록 조용히 빈 문자열을 반환한다. */
$imgDims = static function (string $relativePath): string {
    $size = @getimagesize(FCPATH . $relativePath);
    return $size ? " width=\"{$size[0]}\" height=\"{$size[1]}\"" : '';
};
?>

<div id="popup-layer">
<?php foreach ($activePopups as $p): ?>
<?php /* 초점을 가두지 않는 비모달 오버레이라 dialog 가 아니라 이름 있는 region 으로 노출한다 */ ?>
<div class="site-popup" role="region" aria-label="<?= esc($p['title']) ?> 팝업" id="popup-<?= $p['id'] ?>"
     style="left:<?= (int)$p['pos_x'] ?>px;top:<?= (int)$p['pos_y'] ?>px"
     data-id="<?= $p['id'] ?>">
    <button type="button" class="site-popup-close" aria-label="<?= esc($p['title']) ?> 팝업 닫기">&times;</button>
    <?php if ($p['image_path']): ?>
    <img src="/<?= esc($p['image_path']) ?>" alt="<?= esc($p['title']) ?>" class="site-popup-img"<?= $imgDims($p['image_path']) ?>>
    <?php endif; ?>
    <?php if ($p['content']): ?>
    <?php /* TinyMCE 저장 HTML — 관리자 입력이므로 raw 출력 허용 */ ?>
    <div class="site-popup-body"><?= $p['content'] ?></div>
    <?php endif; ?>
    <div class="site-popup-footer">
        <label class="site-popup-today" for="popup-hide-<?= $p['id'] ?>">
            <input type="checkbox" id="popup-hide-<?= $p['id'] ?>" class="popup-hide-today" data-id="<?= $p['id'] ?>">
            오늘 하루 보지 않기
        </label>
    </div>
</div>
<?php endforeach; ?>
</div>
