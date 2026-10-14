<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = $ad ? '광고 수정' : '광고 등록' ?>

<?= $this->section('content') ?>

<div class="card" style="max-width:640px">
    <div class="card-body">
        <form method="post" action="<?= $ad ? "/admin/ads/{$ad['id']}/edit" : '/admin/ads/create' ?>">
            <?= csrf_field() ?>

            <!-- 이름 -->
            <div class="mb-3">
                <label for="ads-form-name" class="form-label fw-semibold">이름 <span class="text-danger">*</span></label>
                <input id="ads-form-name" type="text" name="name" class="form-control" maxlength="100" required
                       value="<?= esc(old('name', $ad['name'] ?? '')) ?>"
                       aria-describedby="ads-form-name-help">
                <div class="form-text" id="ads-form-name-help">관리자 화면에서 구분하기 위한 이름입니다(방문자에게는 보이지 않음).</div>
            </div>

            <!-- 위치 -->
            <div class="mb-3">
                <label for="ads-form-position" class="form-label fw-semibold">위치 <span class="text-danger">*</span></label>
                <select id="ads-form-position" name="position" class="form-select" required>
                    <?php foreach ($positions as $val => $label): ?>
                    <option value="<?= $val ?>" <?= old('position', $ad['position'] ?? '') === $val ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 광고 코드 -->
            <div class="mb-3">
                <label for="ads-form-code" class="form-label fw-semibold">광고 코드 <span class="text-danger">*</span></label>
                <textarea id="ads-form-code" name="code" class="form-control font-monospace" rows="6" required
                          placeholder="&lt;ins class=&quot;adsbygoogle&quot;&gt;...&lt;/ins&gt;&lt;script&gt;...&lt;/script&gt;"
                          aria-describedby="ads-form-code-help"><?= esc(old('code', $ad['code'] ?? '')) ?></textarea>
                <div class="form-text" id="ads-form-code-help">
                    구글 애드센스 등에서 발급받은 광고 삽입 코드를 그대로 붙여넣으세요. 입력 그대로 화면에 출력됩니다.
                </div>
            </div>

            <!-- 우선순위 -->
            <div class="mb-3">
                <label for="ads-form-priority" class="form-label fw-semibold">우선순위</label>
                <input id="ads-form-priority" type="number" name="priority" class="form-control" style="width:120px"
                       value="<?= esc(old('priority', $ad['priority'] ?? 0)) ?>" min="0">
                <div class="form-text">숫자가 낮을수록 먼저 표시됩니다(같은 위치에 여러 개 등록 시).</div>
            </div>

            <!-- 운영 상태 -->
            <div class="mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1"
                           <?= old('is_active', $ad['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="isActive">운영 중</label>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= $ad ? '저장' : '등록' ?></button>
                <a href="/admin/ads" class="btn btn-outline-secondary">취소</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
