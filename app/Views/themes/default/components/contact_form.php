<?php
/** @var array<string, string> $errors 서버 검증 실패 메시지 (필드명 => 메시지) */
$errors = session('errors') ?? [];

/** 필드에 오류가 있으면 Bootstrap 무효 표시 클래스를 붙인다 */
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
?>
<?php
/** @var list<array<string, mixed>> $inquiryFields 관리자가 등록한 추가 항목 */
$inquiryFields = $inquiryFields ?? [];
$hasFile       = in_array('file', array_column($inquiryFields, 'field_type'), true);
?>
<form method="post" action="/inquiry/submit" class="needs-validation" novalidate<?= $hasFile ? ' enctype="multipart/form-data"' : '' ?>>
    <?= csrf_field() ?>
    <?php /* 허니팟: 봇만 채운다 */ ?>
    <div style="position:absolute;left:-9999px" aria-hidden="true">
        <label for="inquiry-website">website</label>
        <input type="text" id="inquiry-website" name="website" value="" tabindex="-1" autocomplete="off">
    </div>

    <?php if ($errors !== []): ?>
    <div class="alert alert-danger" role="alert">
        <p class="fw-bold mb-1"><?= esc(lang('Site.contactCheckInput')) ?></p>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="inquiry-name">
                <?= esc(lang('Site.contactName')) ?> <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"><?= esc(lang('Site.required')) ?></span>
            </label>
            <input type="text" id="inquiry-name" name="name" class="form-control<?= $invalid('name') ?>"
                   value="<?= old('name') ?>" required autocomplete="name"
                   aria-describedby="inquiry-name-error">
            <div class="invalid-feedback" id="inquiry-name-error"><?= esc($errors['name'] ?? lang('Site.errName')) ?></div>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="inquiry-phone"><?= esc(lang('Site.contactPhone')) ?></label>
            <input type="tel" id="inquiry-phone" name="phone" class="form-control<?= $invalid('phone') ?>"
                   value="<?= old('phone') ?>" placeholder="010-0000-0000" autocomplete="tel"
                   aria-describedby="inquiry-phone-error">
            <div class="invalid-feedback" id="inquiry-phone-error"><?= esc($errors['phone'] ?? lang('Site.errPhone')) ?></div>
        </div>

        <div class="col-12">
            <label class="form-label" for="inquiry-email">
                <?= esc(lang('Site.contactEmail')) ?> <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"><?= esc(lang('Site.required')) ?></span>
            </label>
            <input type="email" id="inquiry-email" name="email" class="form-control<?= $invalid('email') ?>"
                   value="<?= old('email') ?>" required autocomplete="email"
                   aria-describedby="inquiry-email-error">
            <div class="invalid-feedback" id="inquiry-email-error"><?= esc($errors['email'] ?? lang('Site.errEmail')) ?></div>
        </div>

        <div class="col-12">
            <label class="form-label" for="inquiry-subject"><?= esc(lang('Site.contactSubject')) ?></label>
            <input type="text" id="inquiry-subject" name="subject" class="form-control<?= $invalid('subject') ?>"
                   value="<?= old('subject') ?>" aria-describedby="inquiry-subject-error">
            <div class="invalid-feedback" id="inquiry-subject-error"><?= esc($errors['subject'] ?? lang('Site.errSubject')) ?></div>
        </div>

        <div class="col-12">
            <label class="form-label" for="inquiry-message">
                <?= esc(lang('Site.contactMessage')) ?> <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"><?= esc(lang('Site.required')) ?></span>
            </label>
            <textarea id="inquiry-message" name="message" class="form-control<?= $invalid('message') ?>"
                      rows="6" required aria-describedby="inquiry-message-error"><?= old('message') ?></textarea>
            <div class="invalid-feedback" id="inquiry-message-error"><?= esc($errors['message'] ?? lang('Site.errMessage')) ?></div>
        </div>

        <?= view('themes/default/components/contact_extra_fields', ['inquiryFields' => $inquiryFields, 'errors' => $errors]) ?>

        <div class="col-12">
            <button type="submit" class="btn btn-primary px-5"><?= esc(lang('Site.contactSend')) ?></button>
        </div>
    </div>
</form>
