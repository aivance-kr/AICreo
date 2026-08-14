<?php
/** @var array<string, string> $errors 서버 검증 실패 메시지 (필드명 => 메시지) */
$errors = session('errors') ?? [];

/** 필드에 오류가 있으면 Bootstrap 무효 표시 클래스를 붙인다 */
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
?>
<form method="post" action="/inquiry/submit" class="needs-validation" novalidate>
    <?= csrf_field() ?>

    <?php if ($errors !== []): ?>
    <div class="alert alert-danger" role="alert">
        <p class="fw-bold mb-1">입력한 내용을 확인해주세요.</p>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="inquiry-name">
                이름 <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(필수)</span>
            </label>
            <input type="text" id="inquiry-name" name="name" class="form-control<?= $invalid('name') ?>"
                   value="<?= old('name') ?>" required autocomplete="name"
                   aria-describedby="inquiry-name-error">
            <div class="invalid-feedback" id="inquiry-name-error"><?= esc($errors['name'] ?? '이름을 입력해주세요.') ?></div>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="inquiry-phone">연락처</label>
            <input type="tel" id="inquiry-phone" name="phone" class="form-control<?= $invalid('phone') ?>"
                   value="<?= old('phone') ?>" placeholder="010-0000-0000" autocomplete="tel"
                   aria-describedby="inquiry-phone-error">
            <div class="invalid-feedback" id="inquiry-phone-error"><?= esc($errors['phone'] ?? '연락처 형식을 확인해주세요.') ?></div>
        </div>

        <div class="col-12">
            <label class="form-label" for="inquiry-email">
                이메일 <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(필수)</span>
            </label>
            <input type="email" id="inquiry-email" name="email" class="form-control<?= $invalid('email') ?>"
                   value="<?= old('email') ?>" required autocomplete="email"
                   aria-describedby="inquiry-email-error">
            <div class="invalid-feedback" id="inquiry-email-error"><?= esc($errors['email'] ?? '이메일 주소를 정확히 입력해주세요.') ?></div>
        </div>

        <div class="col-12">
            <label class="form-label" for="inquiry-subject">제목</label>
            <input type="text" id="inquiry-subject" name="subject" class="form-control<?= $invalid('subject') ?>"
                   value="<?= old('subject') ?>" aria-describedby="inquiry-subject-error">
            <div class="invalid-feedback" id="inquiry-subject-error"><?= esc($errors['subject'] ?? '제목을 확인해주세요.') ?></div>
        </div>

        <div class="col-12">
            <label class="form-label" for="inquiry-message">
                문의 내용 <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(필수)</span>
            </label>
            <textarea id="inquiry-message" name="message" class="form-control<?= $invalid('message') ?>"
                      rows="6" required aria-describedby="inquiry-message-error"><?= old('message') ?></textarea>
            <div class="invalid-feedback" id="inquiry-message-error"><?= esc($errors['message'] ?? '문의 내용을 입력해주세요.') ?></div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary px-5">문의 보내기</button>
        </div>
    </div>
</form>
