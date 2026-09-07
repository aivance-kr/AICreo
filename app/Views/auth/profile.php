<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
// 원시 슈퍼글로벌 대신 요청 객체를 경유해 쿼리 파라미터를 읽는다.
$activeTab     = service('request')->getGet('tab') ?? 'info';
$providerLabel = match($user['social_provider'] ?? null) {
    'google' => '구글',
    'kakao'  => '카카오',
    'naver'  => '네이버',
    default  => null,
};
?>

<div class="container py-4" style="max-width:640px">
    <h1 class="h5 mb-4 fw-bold"><i class="bi bi-person-circle me-2" aria-hidden="true"></i>내 정보 수정</h1>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'info' ? 'active' : '' ?>" href="/auth/profile"
               <?= $activeTab === 'info' ? 'aria-current="page"' : '' ?>>기본 정보</a>
        </li>
        <?php if (! $user['social_provider']): ?>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'password' ? 'active' : '' ?>" href="/auth/profile?tab=password"
               <?= $activeTab === 'password' ? 'aria-current="page"' : '' ?>>비밀번호 변경</a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link text-danger <?= $activeTab === 'withdraw' ? 'active' : '' ?>" href="/auth/profile?tab=withdraw"
               <?= $activeTab === 'withdraw' ? 'aria-current="page"' : '' ?>>회원 탈퇴</a>
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
                    <label class="form-label" for="profile-email">이메일</label>
                    <input type="text" id="profile-email" class="form-control" value="<?= esc($user['email']) ?>" disabled
                           <?= $providerLabel ? 'aria-describedby="profile-email-help"' : '' ?>>
                    <?php if ($providerLabel): ?>
                    <div class="form-text" id="profile-email-help">
                        <i class="bi bi-link-45deg" aria-hidden="true"></i> <?= esc($providerLabel) ?> 소셜 로그인 연동 계정
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="profile-nickname">
                        닉네임 <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(필수)</span>
                    </label>
                    <input type="text" id="profile-nickname" name="nickname" class="form-control"
                           value="<?= esc(old('nickname', $user['nickname'])) ?>"
                           required minlength="2" maxlength="20" autocomplete="nickname">
                </div>

                <div class="mb-3 text-muted small">
                    <i class="bi bi-clock me-1" aria-hidden="true"></i>가입일: <?= esc(substr($user['created_at'], 0, 10)) ?>
                    <?php if ($user['last_login']): ?>
                    &nbsp;·&nbsp; 최근 로그인: <?= esc(substr($user['last_login'], 0, 16)) ?>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm px-4">저장</button>
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
                        현재 비밀번호 <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(필수)</span>
                    </label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="new_password">
                        새 비밀번호 <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(필수)</span>
                    </label>
                    <input type="password" name="new_password" id="new_password" class="form-control"
                           required minlength="8" autocomplete="new-password" aria-describedby="new-password-help">
                    <div class="form-text" id="new-password-help">8자 이상</div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="confirm_password">
                        새 비밀번호 확인 <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(필수)</span>
                    </label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                           required autocomplete="new-password" aria-describedby="pw-match">
                    <!-- 일치 여부는 입력 중 갱신되므로 라이브 리전으로 읽힌다 -->
                    <div id="pw-match" class="form-text" aria-live="polite"></div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm px-4">변경</button>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-danger">
        <div class="card-body">
            <h2 class="h6 text-danger">회원 탈퇴</h2>
            <p class="text-muted small">탈퇴하면 계정 정보는 비식별화되며, 작성한 게시글과 댓글은 유지됩니다. 이 작업은 되돌릴 수 없습니다.</p>
            <form method="post" action="/auth/withdraw">
                <?= csrf_field() ?>
                <?php if (! $user['social_provider']): ?>
                <div class="mb-3">
                    <label class="form-label" for="withdraw-password">현재 비밀번호</label>
                    <input type="password" id="withdraw-password" name="password" class="form-control" required autocomplete="current-password">
                </div>
                <?php endif; ?>
                <div class="mb-4">
                    <label class="form-label" for="withdraw-confirmation">확인을 위해 <strong>탈퇴</strong>를 입력해주세요</label>
                    <input type="text" id="withdraw-confirmation" name="confirmation" class="form-control" required autocomplete="off">
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-danger btn-sm px-4">회원 탈퇴</button>
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
const np = document.getElementById('new_password');
const cp = document.getElementById('confirm_password');
if (cp) {
    cp.addEventListener('input', function () {
        const msg = document.getElementById('pw-match');
        if (! this.value) { msg.textContent = ''; return; }
        if (this.value === np.value) {
            msg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle" aria-hidden="true"></i> 비밀번호가 일치합니다.</span>';
        } else {
            msg.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle" aria-hidden="true"></i> 비밀번호가 일치하지 않습니다.</span>';
        }
    });
}
</script>
<?= $this->endSection() ?>
