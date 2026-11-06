<?= $this->extend('layouts/admin') ?>
<?php $this->setVar('pageTitle', '문의 폼 항목') ?>
<?= $this->section('content') ?>

<?php
use App\Models\InquiryFieldModel;

$formType = (string) old('field_type', $editing['field_type'] ?? 'text');
?>

<div class="alert alert-info small">
    이름·이메일·연락처·제목·내용은 항상 표시되는 기본 항목입니다. 여기서 추가한 항목은 그 아래에 순서대로 나타나고,
    제출 내용은 문의 상세·CSV에 함께 저장됩니다. 항목을 지워도 이미 받은 문의의 값은 그대로 보입니다.
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 table-stack align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">항목</th>
                            <th scope="col">종류</th>
                            <th scope="col">필수</th>
                            <th scope="col">상태</th>
                            <th scope="col"><span class="visually-hidden">관리</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fields)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">추가 항목이 없습니다. 기본 항목만 표시됩니다.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($fields as $f): ?>
                        <tr class="<?= (int) $f['is_active'] === 1 ? '' : 'text-muted' ?>">
                            <td data-label="항목"><?= esc($f['label']) ?></td>
                            <td data-label="종류"><?= esc(InquiryFieldModel::TYPES[$f['field_type']] ?? $f['field_type']) ?></td>
                            <td data-label="필수"><?= (int) $f['is_required'] === 1 ? '필수' : '-' ?></td>
                            <td data-label="상태"><span class="badge <?= (int) $f['is_active'] === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int) $f['is_active'] === 1 ? '표시' : '숨김' ?></span></td>
                            <td data-label="" class="cell-actions text-nowrap">
                                <?php foreach (['up' => ['↑', '위로'], 'down' => ['↓', '아래로']] as $dir => [$mark, $name]): ?>
                                <form method="post" action="/admin/inquiry-fields/<?= (int) $f['id'] ?>/<?= $dir ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="<?= esc($f['label']) ?> <?= $name ?> 이동"><?= $mark ?></button>
                                </form>
                                <?php endforeach; ?>
                                <a href="/admin/inquiry-fields?edit=<?= (int) $f['id'] ?>" class="btn btn-sm btn-outline-secondary">수정</a>
                                <form method="post" action="/admin/inquiry-fields/<?= (int) $f['id'] ?>/toggle" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?= (int) $f['is_active'] === 1 ? '숨기기' : '표시' ?></button>
                                </form>
                                <form method="post" action="/admin/inquiry-fields/<?= (int) $f['id'] ?>/delete" class="d-inline"
                                      onsubmit="return confirm('이 항목을 삭제할까요?')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><?= $editing ? '항목 수정' : '항목 추가' ?></div>
            <div class="card-body">
                <form method="post" action="/admin/inquiry-fields<?= $editing ? '/' . (int) $editing['id'] : '' ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="f-label">항목 이름</label>
                        <input type="text" class="form-control" id="f-label" name="label" maxlength="100" required
                               value="<?= esc((string) old('label', $editing['label'] ?? '')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="f-type">종류</label>
                        <select class="form-select" id="f-type" name="field_type">
                            <?php foreach (InquiryFieldModel::TYPES as $value => $name): ?>
                            <option value="<?= esc($value) ?>" <?= $formType === $value ? 'selected' : '' ?>><?= esc($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="f-options">선택지 <span class="text-muted small">(선택·체크 종류, 한 줄에 하나)</span></label>
                        <textarea class="form-control" id="f-options" name="options" rows="4" maxlength="2000"><?= esc((string) old('options', $editing['options'] ?? '')) ?></textarea>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="f-required" name="is_required" value="1"
                               <?= old('is_required', (string) ($editing['is_required'] ?? '0')) === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="f-required">필수 항목</label>
                    </div>
                    <div class="form-check mb-3">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="form-check-input" id="f-active" name="is_active" value="1"
                               <?= (string) old('is_active', (string) ($editing['is_active'] ?? '1')) === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="f-active">폼에 표시</label>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= $editing ? '저장' : '추가' ?></button>
                    <?php if ($editing): ?><a href="/admin/inquiry-fields" class="btn btn-outline-secondary">취소</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
