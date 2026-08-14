<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '회원 수정' ?>

<?= $this->section('content') ?>

<div class="mb-2"><a href="/admin/users" class="text-muted small"><i class="bi bi-arrow-left" aria-hidden="true"></i> 목록</a></div>

<div class="card" style="max-width:480px">
    <div class="card-header bg-white"><h2 class="h6 mb-0">회원 정보 수정</h2></div>
    <div class="card-body">
        <div class="mb-3">
            <span class="d-block form-label small fw-bold">이메일</span>
            <p class="form-control-plaintext"><?= esc($member['email']) ?></p>
        </div>
        <div class="mb-3">
            <span class="d-block form-label small fw-bold">가입 방식</span>
            <p class="form-control-plaintext">
                <?= $member['social_provider'] ? esc($member['social_provider']) . ' 소셜 로그인' : '이메일' ?>
            </p>
        </div>

        <form method="post" action="/admin/users/<?= $member['id'] ?>/edit">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="users-edit-nickname" class="form-label small">닉네임</label>
                <input id="users-edit-nickname" type="text" name="nickname" class="form-control form-control-sm"
                       value="<?= esc($member['nickname']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="users-edit-role" class="form-label small">역할</label>
                <select id="users-edit-role" name="role" class="form-select form-select-sm">
                    <option value="member" <?= $member['role'] === 'member' ? 'selected' : '' ?>>일반회원</option>
                    <option value="admin"  <?= $member['role'] === 'admin'  ? 'selected' : '' ?>>관리자</option>
                </select>
            </div>
            <div class="mb-4">
                <label for="users-edit-is_active" class="form-label small">상태</label>
                <select id="users-edit-is_active" name="is_active" class="form-select form-select-sm">
                    <option value="1" <?= $member['is_active'] ? 'selected' : '' ?>>활성</option>
                    <option value="0" <?= !$member['is_active'] ? 'selected' : '' ?>>비활성 (로그인 차단)</option>
                </select>
            </div>
            <div class="d-flex gap-2 justify-content-end">
                <a href="/admin/users" class="btn btn-outline-secondary btn-sm">취소</a>
                <button type="submit" class="btn btn-primary btn-sm">저장</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
