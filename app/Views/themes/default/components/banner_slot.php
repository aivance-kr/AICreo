<?php if (!empty($banners)): ?>
<?php
/** 실제 파일에서 가로/세로를 읽어 <img> 에 박아 로드 전후 레이아웃 밀림(CLS)을 막는다.
 *  실패해도 렌더링을 막지 않도록 조용히 빈 문자열을 반환한다. */
$imgDims = static function (string $relativePath): string {
    $size = @getimagesize(FCPATH . $relativePath);
    return $size ? " width=\"{$size[0]}\" height=\"{$size[1]}\"" : '';
};
?>
<div class="container py-3">
    <div class="main-banner-wrap">
        <?php foreach ($banners as $b): ?>
        <?php $alt = trim((string) ($b['alt_text'] ?? '')); ?>
        <?php if ($b['link_url']): ?>
        <a href="<?= esc($b['link_url']) ?>" target="<?= esc($b['link_target']) ?>"<?= $b['link_target'] === '_blank' ? ' rel="noopener"' : '' ?>>
            <img src="/<?= esc($b['image_path']) ?>" alt="<?= esc($alt !== '' ? $alt : '배너') ?>"<?= $imgDims($b['image_path']) ?>>
            <?php if ($b['link_target'] === '_blank'): ?><span class="visually-hidden">(새 창 열림)</span><?php endif; ?>
        </a>
        <?php else: ?>
        <img src="/<?= esc($b['image_path']) ?>" alt="<?= esc($alt) ?>"<?= $imgDims($b['image_path']) ?>>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
