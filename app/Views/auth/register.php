<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="container">
<div class="row justify-content-center my-5">
    <div class="col-sm-5">
        <div class="card">
            <div class="card-body p-4">
                <h1 class="h5 mb-4"><?= esc(lang('Auth.register')) ?></h1>
                <?php $errors = session('errors') ?? []; ?>
                <?php if ($errors !== []): ?>
                    <div class="alert alert-danger" role="alert">
                        <p class="fw-bold mb-1"><?= esc(lang('Auth.checkInput')) ?></p>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="post" action="/auth/register">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="register-email">
                            <?= esc(lang('Auth.email')) ?> <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"><?= esc(lang('Auth.required')) ?></span>
                        </label>
                        <input type="email" id="register-email" name="email" class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                               value="<?= old('email') ?>" required autocomplete="email" aria-describedby="register-email-error">
                        <div class="invalid-feedback" id="register-email-error"><?= esc($errors['email'] ?? lang('Auth.emailInvalid')) ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="register-nickname">
                            <?= esc(lang('Auth.nickname')) ?> <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"><?= esc(lang('Auth.required')) ?></span>
                        </label>
                        <input type="text" id="register-nickname" name="nickname" class="form-control<?= isset($errors['nickname']) ? ' is-invalid' : '' ?>"
                               value="<?= old('nickname') ?>" required autocomplete="nickname" aria-describedby="register-nickname-error">
                        <div class="invalid-feedback" id="register-nickname-error"><?= esc($errors['nickname'] ?? lang('Auth.nicknameRequired')) ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="register-password">
                            <?= esc(lang('Auth.password')) ?> <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"><?= esc(lang('Auth.required')) ?></span>
                        </label>
                        <input type="password" id="register-password" name="password" class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                               required minlength="8" autocomplete="new-password"
                               aria-describedby="register-password-help register-password-error">
                        <div class="form-text" id="register-password-help"><?= esc(lang('Auth.passwordHelp')) ?></div>
                        <div class="invalid-feedback" id="register-password-error"><?= esc($errors['password'] ?? lang('Auth.passwordTooShort')) ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="register-password-confirm">
                            <?= esc(lang('Auth.passwordConfirm')) ?> <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"><?= esc(lang('Auth.required')) ?></span>
                        </label>
                        <input type="password" id="register-password-confirm" name="password_confirm" class="form-control<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>"
                               required autocomplete="new-password" aria-describedby="register-password-confirm-error">
                        <div class="invalid-feedback" id="register-password-confirm-error"><?= esc($errors['password_confirm'] ?? lang('Auth.passwordMismatch')) ?></div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><?= esc(lang('Auth.signUpSubmit')) ?></button>
                </form>
                <div class="text-center mt-3 small">
                    <a href="/auth/login" class="text-decoration-none"><?= esc(lang('Auth.backToLogin')) ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?= $this->endSection() ?>
