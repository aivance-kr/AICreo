<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('pageTitle', '2단계 인증') ?>
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

<hr class="my-4">

<h2 class="h5 fw-semibold">패스키</h2>
<p class="small text-muted">
    지문·얼굴·PIN·보안 키로 로그인합니다. 패스키로 로그인하면 인증 코드(2단계 인증)는 묻지 않습니다.
    기기를 잃어버렸을 때를 대비해 비밀번호 로그인은 그대로 둡니다.
</p>
<ul class="small text-muted ps-3">
    <li>패스키는 <strong>"패스키 추가"를 누른 기기</strong>(또는 그 기기의 Google·Apple 계정, 비밀번호 관리자)에 저장됩니다. 이름은 구분용 메모일 뿐 저장 위치를 정하지 않습니다.</li>
    <li>다른 기기에서도 쓰려면 그 기기에서 로그인한 뒤 이 화면에서 패스키를 하나 더 추가합니다. 같은 Google·Apple 계정으로 패스키가 동기화되는 기기는 추가 없이 바로 쓸 수 있습니다.</li>
    <li>PC에서 휴대전화용 패스키를 만들려면 "패스키 추가"를 누른 뒤 브라우저 창에서 "다른 기기 사용"(휴대전화·태블릿)을 골라 QR 코드를 휴대전화로 찍습니다.</li>
    <li>로그인 화면의 QR 코드는 그 휴대전화에 패스키가 저장돼 있을 때만 통과합니다.</li>
</ul>

<?php if ($passkeys === []): ?>
    <p class="text-muted">등록된 패스키가 없습니다.</p>
<?php else: ?>
    <ul class="list-group mb-3">
        <?php foreach ($passkeys as $passkey): ?>
            <li class="list-group-item">
                <form method="post" action="/admin/security/passkeys/<?= (int) $passkey['id'] ?>/delete"
                      class="d-flex flex-wrap align-items-center gap-2">
                    <?= csrf_field() ?>
                    <div class="me-auto">
                        <div class="fw-semibold"><?= esc($passkey['name']) ?></div>
                        <div class="small text-muted">
                            등록 <?= esc(substr((string) $passkey['created_at'], 0, 10)) ?>
                            · 마지막 사용 <?= $passkey['last_used_at'] ? esc(substr((string) $passkey['last_used_at'], 0, 16)) : '없음' ?>
                        </div>
                    </div>
                    <?php if ($hasPassword): ?>
                        <input type="password" id="passkey-del-password-<?= (int) $passkey['id'] ?>" name="password" class="form-control form-control-sm w-auto" placeholder="비밀번호" aria-label="비밀번호" required autocomplete="current-password">
                    <?php endif; ?>
                    <?php if ($totpEnabled): ?>
                        <input type="text" id="passkey-del-code-<?= (int) $passkey['id'] ?>" name="code" class="form-control form-control-sm w-auto" placeholder="인증 코드" aria-label="인증 코드" required autocomplete="one-time-code">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if (! $hasPassword && ! $totpEnabled): ?>
    <p class="small text-muted">비밀번호가 없는 계정은 본인 확인 수단이 없어 패스키를 추가할 수 없습니다. 위에서 2단계 인증을 먼저 켜 주세요.</p>
<?php else: ?>
    <form id="passkey-add" class="d-flex flex-column gap-3" style="max-width: 420px;" hidden>
        <h3 class="h6 fw-semibold mb-0">이 기기에 패스키 추가</h3>
        <div>
            <label for="passkey-add-name" class="form-label mb-1">패스키 이름</label>
            <input type="text" id="passkey-add-name" name="name" class="form-control" placeholder="예: 사무실 PC 크롬, 갤럭시 S25" maxlength="100" required aria-describedby="passkey-add-name-help">
            <div id="passkey-add-name-help" class="form-text">나중에 목록에서 알아볼 이름입니다. 패스키가 실제로 저장되는 기기 이름을 적으세요.</div>
        </div>
        <?php if ($hasPassword): ?>
            <div>
                <label for="passkey-add-password" class="form-label mb-1">비밀번호</label>
                <input type="password" id="passkey-add-password" name="password" class="form-control" required autocomplete="current-password" aria-describedby="passkey-add-password-help">
                <div id="passkey-add-password-help" class="form-text">지금 로그인한 관리자 계정의 로그인 비밀번호입니다. 본인 확인에만 씁니다.</div>
            </div>
        <?php endif; ?>
        <?php if ($totpEnabled): ?>
            <div>
                <label for="passkey-add-code" class="form-label mb-1">인증 코드</label>
                <input type="text" id="passkey-add-code" name="code" class="form-control" required autocomplete="one-time-code" inputmode="numeric" aria-describedby="passkey-add-code-help">
                <div id="passkey-add-code-help" class="form-text">인증 앱(Google Authenticator 등)에 보이는 6자리 숫자입니다. 방금 로그인에 쓴 코드는 다시 쓸 수 없으니 새 코드가 뜬 뒤 입력하세요.</div>
            </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">패스키 추가</button>
        <div class="form-text mt-0">누르면 브라우저가 지문·얼굴·PIN 을 묻습니다. 승인하면 목록에 나타납니다.</div>
        <div id="passkey-add-error" class="text-danger small" role="alert"></div>
    </form>
    <p id="passkey-unsupported" class="small text-muted">이 브라우저는 패스키를 지원하지 않습니다. 최신 Chrome·Safari·Edge·Firefox 에서 이용하세요.</p>
<?php endif; ?>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="/js/passkey.js"></script>
<script>
(function () {
    var form = document.getElementById('passkey-add');
    if (!form || !window.Passkey || !Passkey.supported()) { return; }
    form.hidden = false;
    document.getElementById('passkey-unsupported').hidden = true;
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var error = document.getElementById('passkey-add-error');
        error.textContent = '';
        Passkey.register(form, <?= json_encode(csrf_hash()) ?>).catch(function (e) {
            if (!e || e.name === 'NotAllowedError') { return; }
            // InvalidStateError: 이 기기(또는 동기화된 계정)에 이미 등록된 패스키가 있다 — 브라우저의 영어 문구 대신 안내한다
            error.textContent = e.name === 'InvalidStateError'
                ? '이 기기(또는 연결된 Google·Apple 계정)에는 이미 이 계정의 패스키가 있습니다. 추가하지 않아도 로그인 화면에서 "패스키로 로그인"을 바로 쓸 수 있습니다.'
                : (e.message || '패스키를 등록하지 못했습니다.');
        });
    });
})();
</script>
<?= $this->endSection() ?>
