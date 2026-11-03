<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="container">
<div class="row justify-content-center my-5">
    <div class="col-sm-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h1 class="h5 mb-4 fw-bold">2단계 인증</h1>

                <?php if (session()->has('error')): ?>
                    <div class="alert alert-danger" role="alert"><?= esc(session('error')) ?></div>
                <?php endif; ?>

                <?php if ($locked): ?>
                    <div class="alert alert-warning" role="alert">인증에 여러 번 실패해 잠시 잠겼습니다. 잠시 뒤 다시 로그인해 주세요.</div>
                    <a href="/auth/login" class="btn btn-outline-secondary w-100">로그인으로 돌아가기</a>
                <?php else: ?>
                    <form method="post" action="/auth/2fa">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="twofa-code">인증 앱의 6자리 코드 또는 복구 코드</label>
                            <input type="text" id="twofa-code" name="code" class="form-control" required
                                   autocomplete="one-time-code" inputmode="text" autofocus>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">확인</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>
<?= $this->endSection() ?>
