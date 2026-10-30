<?php
/**
 * 글 공유 버튼 모음 — 링크 복사 · 카카오톡 · X · 페이스북.
 *
 * @var string $url        canonical 주소
 * @var string $title      글 제목
 * @var string $kakaoJsKey 카카오 JavaScript 키 (비어 있으면 카카오톡 버튼 숨김)
 */
$encodedUrl = rawurlencode($url);
?>
<div class="post-share d-flex flex-wrap gap-2 mb-2" role="group" aria-label="공유하기">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="share-btn" data-url="<?= esc($url, 'attr') ?>">
        <i class="bi bi-link-45deg" aria-hidden="true"></i> <span>링크 복사</span>
    </button>
    <?php if ($kakaoJsKey !== ''): ?>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-kakao-share
            data-kakao-key="<?= esc($kakaoJsKey, 'attr') ?>" data-url="<?= esc($url, 'attr') ?>" data-title="<?= esc($title, 'attr') ?>">
        <i class="bi bi-chat-fill" aria-hidden="true"></i> 카카오톡
    </button>
    <?php endif; ?>
    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener noreferrer"
       href="https://twitter.com/intent/tweet?url=<?= $encodedUrl ?>&amp;text=<?= rawurlencode($title) ?>">
        <i class="bi bi-twitter-x" aria-hidden="true"></i> X<span class="visually-hidden"> 로 공유 (새 창 열림)</span>
    </a>
    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener noreferrer"
       href="https://www.facebook.com/sharer/sharer.php?u=<?= $encodedUrl ?>">
        <i class="bi bi-facebook" aria-hidden="true"></i> 페이스북<span class="visually-hidden"> 으로 공유 (새 창 열림)</span>
    </a>
</div>
