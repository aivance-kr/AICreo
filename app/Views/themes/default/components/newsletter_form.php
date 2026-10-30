<?php
/**
 * 뉴스레터 구독 폼. $inputId 로 한 화면에 여러 번 넣어도 id 가 겹치지 않게 한다.
 *
 * @var string $inputId
 * @var bool   $dark    어두운 배경(푸터)에 놓을지
 */
$inputId = $inputId ?? 'newsletter-email';
$dark    = $dark ?? false;
?>
<form method="post" action="<?= esc(site_url('newsletter/subscribe'), 'attr') ?>" class="newsletter-form">
    <?= csrf_field() ?>
    <label for="<?= esc($inputId, 'attr') ?>" class="form-label small fw-semibold mb-1">새 글 알림 받기</label>
    <div class="input-group input-group-sm">
        <input type="email" name="email" id="<?= esc($inputId, 'attr') ?>" class="form-control" placeholder="이메일 주소"
               required maxlength="190" autocomplete="email" aria-describedby="<?= esc($inputId, 'attr') ?>-privacy">
        <button type="submit" class="btn <?= $dark ? 'btn-light' : 'btn-primary' ?>">구독</button>
    </div>
    <?php /* 허니팟 — 사람은 보지 못하고 봇만 채운다 */ ?>
    <div style="position:absolute;left:-9999px" aria-hidden="true">
        <label for="<?= esc($inputId, 'attr') ?>-hp">웹사이트</label>
        <input type="text" name="<?= esc(\App\Controllers\Front\NewsletterController::HONEYPOT_FIELD, 'attr') ?>" id="<?= esc($inputId, 'attr') ?>-hp" value="" tabindex="-1" autocomplete="off">
    </div>
    <p id="<?= esc($inputId, 'attr') ?>-privacy" class="small mb-0 mt-1 <?= $dark ? 'text-white-50' : 'text-muted' ?>">
        <?= esc(\App\Libraries\NewsletterMailer::PRIVACY_NOTICE) ?>
    </p>
</form>
