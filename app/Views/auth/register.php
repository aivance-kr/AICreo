<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="container">
<div class="row justify-content-center my-5">
    <div class="col-sm-5">
        <div class="card">
            <div class="card-body p-4">
                <h1 class="h5 mb-4">회원가입</h1>
                <?php $errors = session('errors') ?? []; ?>
                <?php if ($errors !== []): ?>
                    <div class="alert alert-danger" role="alert">
                        <p class="fw-bold mb-1">입력한 내용을 확인해주세요.</p>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="post" action="/auth/register">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="register-email">
                            이메일 <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(필수)</span>
                        </label>
                        <input type="email" id="register-email" name="email" class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                               value="<?= old('email') ?>" required autocomplete="email" aria-describedby="register-email-error">
                        <div class="invalid-feedback" id="register-email-error"><?= esc($errors['email'] ?? '이메일 주소를 정확히 입력해주세요.') ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="register-nickname">
                            닉네임 <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(필수)</span>
                        </label>
                        <input type="text" id="register-nickname" name="nickname" class="form-control<?= isset($errors['nickname']) ? ' is-invalid' : '' ?>"
                               value="<?= old('nickname') ?>" required autocomplete="nickname" aria-describedby="register-nickname-error">
                        <div class="invalid-feedback" id="register-nickname-error"><?= esc($errors['nickname'] ?? '닉네임을 입력해주세요.') ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="register-password">
                            비밀번호 <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(필수)</span>
                        </label>
                        <input type="password" id="register-password" name="password" class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                               required minlength="8" autocomplete="new-password"
                               aria-describedby="register-password-help register-password-error">
                        <div class="form-text" id="register-password-help">8자 이상 입력해주세요.</div>
                        <div class="invalid-feedback" id="register-password-error"><?= esc($errors['password'] ?? '비밀번호는 8자 이상이어야 합니다.') ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="register-password-confirm">
                            비밀번호 확인 <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(필수)</span>
                        </label>
                        <input type="password" id="register-password-confirm" name="password_confirm" class="form-control<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>"
                               required autocomplete="new-password" aria-describedby="register-password-confirm-error">
                        <div class="invalid-feedback" id="register-password-confirm-error"><?= esc($errors['password_confirm'] ?? '비밀번호가 일치하지 않습니다.') ?></div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">가입하기</button>
                </form>
                <div class="text-center mt-3 small">
                    <a href="/auth/login" class="text-decoration-none">로그인으로 돌아가기</a>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?= $this->endSection() ?>
