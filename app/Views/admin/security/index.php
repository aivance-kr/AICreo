<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '2단계 인증' ?>
<?= $this->section('content') ?>

<?php if (! empty($recoveryCodes)): ?>
<div class="alert alert-warning">
    <p class="fw-semibold mb-2">복구 코드 — 지금만 볼 수 있습니다. 안전한 곳에 보관하세요.</p>
    <p class="small mb-2">인증 앱을 쓸 수 없을 때 코드 1개로 로그인할 수 있고, 한 번 쓰면 사라집니다.</p>
    <ul class="list-unstyled font-monospace mb-0">
        <?php foreach ($recoveryCodes as $recoveryCode): ?>
            <li><?= esc($recoveryCode) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (! $ready): ?>
    <div class="alert alert-danger">
        서버 <code>.env</code> 에 <code>encryption.key</code> 가 없어 2단계 인증을 켤 수 없습니다.
        <code>php spark key:generate</code> 로 키를 만든 뒤 다시 시도하세요.
    </div>
<?php elseif ($enabled): ?>
    <div class="alert alert-success">2단계 인증이 켜져 있습니다. 남은 복구 코드 <?= (int) $remaining ?>개.</div>

    <div class="row g-4">
        <div class="col-lg-6">
            <h2 class="h6 fw-semibold">복구 코드 다시 발급</h2>
            <form method="post" action="/admin/security/recovery" class="d-flex flex-column gap-2">
                <?= csrf_field() ?>
                <?php if ($hasPassword): ?>
                    <input type="password" name="password" id="sec-recovery-password" class="form-control" placeholder="비밀번호" aria-label="비밀번호" required autocomplete="current-password">
                <?php endif; ?>

                <input id="sec-recovery-code" type="text" name="code" class="form-control" placeholder="인증 코드" aria-label="인증 코드" required autocomplete="one-time-code">
                <button type="submit" class="btn btn-outline-primary text-nowrap">재발급</button>
            </form>
        </div>
        <div class="col-lg-6">
            <h2 class="h6 fw-semibold">2단계 인증 끄기</h2>
            <form method="post" action="/admin/security/disable" class="d-flex flex-column gap-2">
                <?= csrf_field() ?>
                <?php if ($hasPassword): ?>
                    <input id="sec-disable-password" type="password" name="password" class="form-control" placeholder="비밀번호" aria-label="비밀번호" required autocomplete="current-password">
                <?php endif; ?>
                <input id="sec-disable-code" type="text" name="code" class="form-control" placeholder="인증 코드" aria-label="인증 코드" required autocomplete="one-time-code">
                <button type="submit" class="btn btn-outline-danger">끄기</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <p>인증 앱(Google Authenticator, 1Password 등)으로 QR 코드를 스캔한 뒤, 앱에 표시된 6자리 코드를 입력하면 켜집니다.</p>
            <div class="mb-3" style="max-width: 220px;"><?= $qr ?></div>
            <p class="small text-muted">QR 을 못 찍으면 키를 직접 입력하세요: <code><?= esc($secret) ?></code></p>
            <form method="post" action="/admin/security/enable" class="d-flex flex-column gap-2">
                <?= csrf_field() ?>
                <?php if ($hasPassword): ?>
                    <input type="password" name="password" id="sec-enable-password" class="form-control" placeholder="비밀번호" aria-label="비밀번호" required autocomplete="current-password">
                <?php endif; ?>

                <input id="sec-enable-code" type="text" name="code" class="form-control" placeholder="6자리 코드" aria-label="6자리 확인 코드" required
                       autocomplete="one-time-code" inputmode="numeric" maxlength="6">
                <button type="submit" class="btn btn-primary text-nowrap">켜기</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
