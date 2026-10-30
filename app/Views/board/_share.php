<?php
/**
 * 글 공유 버튼 모음 — 링크 복사 · 카카오톡 · X · 페이스북.
 *
 * @var string $url        canonical 주소 (SNS 공유용)
 * @var string $shortUrl   짧은 주소 (/{글ID}, 링크 복사용)
 * @var string $title      글 제목
 * @var string $kakaoJsKey 카카오 JavaScript 키 (비어 있으면 카카오톡 버튼 숨김)
 */
$encodedUrl = rawurlencode($url);
?>
<div class="post-share" role="group" aria-label="공유하기">
    <button type="button" class="post-share__btn" id="share-btn" data-url="<?= esc($shortUrl, 'attr') ?>" title="링크 복사">
        <i class="bi bi-link-45deg" aria-hidden="true"></i><span class="visually-hidden">링크 복사</span>
    </button>
    <?php if ($kakaoJsKey !== ''): ?>
    <button type="button" class="post-share__btn" data-kakao-share title="카카오톡 공유"
            data-kakao-key="<?= esc($kakaoJsKey, 'attr') ?>" data-url="<?= esc($url, 'attr') ?>" data-title="<?= esc($title, 'attr') ?>">
        <i class="bi bi-chat-fill" aria-hidden="true"></i><span class="visually-hidden">카카오톡 공유</span>
    </button>
    <?php endif; ?>
    <a class="post-share__btn" target="_blank" rel="noopener noreferrer" title="X 공유"
       href="https://twitter.com/intent/tweet?url=<?= $encodedUrl ?>&amp;text=<?= rawurlencode($title) ?>">
        <i class="bi bi-twitter-x" aria-hidden="true"></i><span class="visually-hidden">X 로 공유 (새 창 열림)</span>
    </a>
    <a class="post-share__btn" target="_blank" rel="noopener noreferrer" title="페이스북 공유"
       href="https://www.facebook.com/sharer/sharer.php?u=<?= $encodedUrl ?>">
        <i class="bi bi-facebook" aria-hidden="true"></i><span class="visually-hidden">페이스북으로 공유 (새 창 열림)</span>
    </a>
</div>
