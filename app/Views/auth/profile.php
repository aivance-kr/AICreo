<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
// 원시 슈퍼글로벌 대신 요청 객체를 경유해 쿼리 파라미터를 읽는다.
$activeTab     = service('request')->getGet('tab') ?? 'info';
$providerLabel = match($user['social_provider'] ?? null) {
    'google' => lang('Auth.providerGoogle'),
    'kakao'  => lang('Auth.providerKakao'),
    'naver'  => lang('Auth.providerNaver'),
    default  => null,
};
?>

<div class="container py-4" style="max-width:640px">
    <h1 class="h5 mb-4 fw-bold"><i class="bi bi-person-circle me-2" aria-hidden="true"></i><?= esc(lang('Auth.profileHeading')) ?></h1>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'info' ? 'active' : '' ?>" href="/auth/profile"
               <?= $activeTab === 'info' ? 'aria-current="page"' : '' ?>><?= esc(lang('Auth.tabInfo')) ?></a>
        </li>
        <?php if (! $user['social_provider']): ?>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'password' ? 'active' : '' ?>" href="/auth/profile?tab=password"
               <?= $activeTab === 'password' ? 'aria-current="page"' : '' ?>><?= esc(lang('Auth.tabPassword')) ?></a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link text-danger <?= $activeTab === 'withdraw' ? 'active' : '' ?>" href="/auth/profile?tab=withdraw"
               <?= $activeTab === 'withdraw' ? 'aria-current="page"' : '' ?>><?= esc(lang('Auth.tabWithdraw')) ?></a>
        </li>
    </ul>

    <?php if ($activeTab === 'info'): ?>
    <!-- ── 기본 정보 탭 ── -->
    <div class="card">
        <div class="card-body">
            <form method="post" action="/auth/profile">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="info">

                <div class="mb-3">
                    <label class="form-label" for="profile-email"><?= esc(lang('Auth.email')) ?></label>
                    <input type="text" id="profile-email" class="form-control" value="<?= esc($user['email']) ?>" disabled
                           <?= $providerLabel ? 'aria-describedby="profile-email-help"' : '' ?>>
                    <?php if ($providerLabel): ?>
                    <div class="form-text" id="profile-email-help">
                        <i class="bi bi-link-45deg" aria-hidden="true"></i> <?= esc($providerLabel) ?> <?= esc(lang('Auth.socialLinked')) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="profile-nickname">
                        <?= esc(lang('Auth.nickname')) ?> <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"><?= esc(lang('Auth.required')) ?></span>
                    </label>
                    <input type="text" id="profile-nickname" name="nickname" class="form-control"
                           value="<?= esc(old('nickname', $user['nickname'])) ?>"
                           required minlength="2" maxlength="20" autocomplete="nickname">
                </div>

                <div class="mb-3 text-muted small">
                    <i class="bi bi-clock me-1" aria-hidden="true"></i><?= esc(lang('Auth.joinedAt')) ?>: <?= esc(substr($user['created_at'], 0, 10)) ?>
                    <?php if ($user['last_login']): ?>
                    &nbsp;·&nbsp; <?= esc(lang('Auth.lastLogin')) ?>: <?= esc(substr($user['last_login'], 0, 16)) ?>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm px-4"><?= esc(lang('Auth.save')) ?></button>
                </div>
            </form>
        </div>
    </div>

    <?php elseif ($activeTab === 'password' && ! $user['social_provider']): ?>
    <!-- ── 비밀번호 변경 탭 ── -->
    <div class="card">
        <div class="card-body">
            <form method="post" action="/auth/profile">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="password">

                <div class="mb-3">
                    <label class="form-label" for="current_password">
                        <?= esc(lang('Auth.currentPassword')) ?> <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"><?= esc(lang('Auth.required')) ?></span>
                    </label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="new_password">
                        <?= esc(lang('Auth.newPassword')) ?> <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"><?= esc(lang('Auth.required')) ?></span>
                    </label>
                    <input type="password" name="new_password" id="new_password" class="form-control"
                           required minlength="8" autocomplete="new-password" aria-describedby="new-password-help">
                    <div class="form-text" id="new-password-help"><?= esc(lang('Auth.passwordMin8')) ?></div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="confirm_password">
                        <?= esc(lang('Auth.newPasswordConfirm')) ?> <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"><?= esc(lang('Auth.required')) ?></span>
                    </label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                           required autocomplete="new-password" aria-describedby="pw-match">
                    <!-- 일치 여부는 입력 중 갱신되므로 라이브 리전으로 읽힌다 -->
                    <div id="pw-match" class="form-text" aria-live="polite"></div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm px-4"><?= esc(lang('Auth.change')) ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-danger">
        <div class="card-body">
            <h2 class="h6 text-danger"><?= esc(lang('Auth.tabWithdraw')) ?></h2>
            <p class="text-muted small"><?= esc(lang('Auth.withdrawNotice')) ?></p>
            <form method="post" action="/auth/withdraw">
                <?= csrf_field() ?>
                <?php if (! $user['social_provider']): ?>
                <div class="mb-3">
                    <label class="form-label" for="withdraw-password"><?= esc(lang('Auth.currentPassword')) ?></label>
                    <input type="password" id="withdraw-password" name="password" class="form-control" required autocomplete="current-password">
                </div>
                <?php endif; ?>
                <div class="mb-4">
                    <label class="form-label" for="withdraw-confirmation"><?= esc(lang('Auth.withdrawConfirmLabelPre')) ?><strong><?= esc(lang('Auth.withdrawWord')) ?></strong><?= esc(lang('Auth.withdrawConfirmLabelPost')) ?></label>
                    <input type="text" id="withdraw-confirmation" name="confirmation" class="form-control" required autocomplete="off">
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-danger btn-sm px-4"><?= esc(lang('Auth.tabWithdraw')) ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
// 비밀번호 확인 실시간 체크
const pwMatchText = <?= json_encode(lang('Auth.passwordMatch'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
const pwMismatchText = <?= json_encode(lang('Auth.passwordMismatch'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
const np = document.getElementById('new_password');
const cp = document.getElementById('confirm_password');
if (cp) {
    cp.addEventListener('input', function () {
        const msg = document.getElementById('pw-match');
        if (! this.value) { msg.textContent = ''; return; }
        if (this.value === np.value) {
            msg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle" aria-hidden="true"></i> ' + pwMatchText + '</span>';
        } else {
            msg.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle" aria-hidden="true"></i> ' + pwMismatchText + '</span>';
        }
    });
}
</script>
<?= $this->endSection() ?>
