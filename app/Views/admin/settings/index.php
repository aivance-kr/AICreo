<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '사이트 설정' ?>
<?= $this->section('content') ?>

<!-- 탭 -->
<ul class="nav nav-tabs mb-4">
    <?php foreach (['general' => '기본', 'contact' => '연락처', 'sns' => 'SNS', 'seo' => 'SEO', 'footer' => '푸터'] as $g => $label): ?>
    <li class="nav-item">
        <a class="nav-link <?= $group === $g ? 'active' : '' ?>" href="/admin/settings/<?= $g ?>"><?= $label ?></a>
    </li>
    <?php endforeach; ?>
    <li class="nav-item">
        <a class="nav-link" href="/admin/settings/oauth">소셜 로그인</a>
    </li>
</ul>

<div class="card border-0 shadow-sm" style="max-width:600px">
    <div class="card-body p-4">
        <form method="post" action="/admin/settings/<?= esc($group) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <?php
            // 조직 스키마 타입(org_type) 선택 옵션 (schema.org)
            $orgTypeOptions = [
                'Organization'        => '일반 조직 (Organization)',
                'LocalBusiness'       => '지역 사업체 (LocalBusiness)',
                'Corporation'         => '기업 (Corporation)',
                'ProfessionalService' => '전문 서비스 (ProfessionalService)',
                'Store'               => '상점 (Store)',
            ];
            ?>
            <?php foreach ($settings as $s): ?>
            <div class="mb-3">
                <?php if ($s['type'] === 'boolean'): ?>
                    <div class="form-check form-switch">
                        <input type="hidden" name="<?= esc($s['key']) ?>" value="0">
                        <input type="checkbox" class="form-check-input" role="switch"
                               id="chk_<?= esc($s['key']) ?>" name="<?= esc($s['key']) ?>" value="1"
                               <?= $s['value'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-semibold" for="chk_<?= esc($s['key']) ?>"><?= esc($s['label']) ?></label>
                    </div>
                <?php else: ?>
                <?php if ($s['type'] === 'image'): ?>
                    <?php /* 이미지 항목은 컨트롤이 여러 개(선택 버튼 + 업로드)라 단일 label 이 아니라 그룹 이름으로 낸다 */ ?>
                    <span class="d-block form-label small fw-semibold" id="lbl_<?= esc($s['key']) ?>"><?= esc($s['label']) ?></span>
                <?php else: ?>
                    <label class="form-label small fw-semibold" for="set_<?= esc($s['key']) ?>"><?= esc($s['label']) ?></label>
                <?php endif; ?>
                <?php if ($s['type'] === 'textarea'): ?>
                    <textarea name="<?= esc($s['key']) ?>" id="set_<?= esc($s['key']) ?>" class="form-control form-control-sm" rows="3"><?= esc($s['value']) ?></textarea>
                <?php elseif ($s['key'] === 'org_type'): ?>
                    <select name="<?= esc($s['key']) ?>" id="set_<?= esc($s['key']) ?>" class="form-select form-select-sm">
                        <?php foreach ($orgTypeOptions as $val => $label): ?>
                        <option value="<?= esc($val) ?>" <?= $s['value'] === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($s['type'] === 'image'): ?>
                    <?php /* 선택한 이미지가 바뀌면 스크린리더에도 알린다 */ ?>
                    <div id="preview_<?= esc($s['key']) ?>" class="mb-1" aria-live="polite">
                        <?php if ($s['value']): ?>
                            <img src="/<?= esc($s['value']) ?>" style="max-height:60px" class="img-thumbnail" alt="<?= esc($s['label']) ?> 현재 이미지">
                        <?php endif; ?>
                    </div>
                    <input type="hidden" id="input_<?= esc($s['key']) ?>" name="<?= esc($s['key']) ?>" value="<?= esc($s['value']) ?>">
                    <div class="d-flex gap-2" role="group" aria-labelledby="lbl_<?= esc($s['key']) ?>">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openMediaPicker('<?= esc($s['key']) ?>')">
                            <i class="bi bi-images" aria-hidden="true"></i> 미디어에서 선택
                        </button>
                        <?php /* d-none 은 키보드 초점을 받지 못한다. 화면에서만 감추고 레이블로 조작한다. */ ?>
                        <input type="file" id="upload_<?= esc($s['key']) ?>" accept="image/*" class="visually-hidden"
                               onchange="uploadSettingImage(this, '<?= esc($s['key']) ?>')">
                        <label class="btn btn-outline-primary btn-sm mb-0" for="upload_<?= esc($s['key']) ?>">
                            <i class="bi bi-upload" aria-hidden="true"></i> 직접 업로드
                        </label>
                    </div>
                <?php else: ?>
                    <input type="text" name="<?= esc($s['key']) ?>" id="set_<?= esc($s['key']) ?>" class="form-control form-control-sm" value="<?= esc($s['value']) ?>">
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <div class="text-end">
                <button type="submit" class="btn btn-primary btn-sm px-4">저장</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= $this->include('admin/settings/_media_picker_modal') ?>
<script>
let mediaPickerTargetKey = null;
let mediaPickerModalInstance = null;

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';

    return div.innerHTML;
}

function applyImageToField(key, path) {
    document.getElementById('input_' + key).value = path;
    document.getElementById('preview_' + key).innerHTML =
        '<img src="/' + path + '" style="max-height:60px" class="img-thumbnail" alt="선택된 이미지">';
}

function openMediaPicker(key) {
    mediaPickerTargetKey = key;
    if (! mediaPickerModalInstance) {
        mediaPickerModalInstance = new bootstrap.Modal(document.getElementById('mediaPickerModal'));
    }
    loadMediaPickerList(1);
    mediaPickerModalInstance.show();
}

function selectMediaPickerItem(path) {
    applyImageToField(mediaPickerTargetKey, path);
    mediaPickerModalInstance.hide();
}

async function loadMediaPickerList(page) {
    const grid = document.getElementById('mediaPickerGrid');
    grid.innerHTML = '<div class="col-12 text-center text-muted py-4">불러오는 중...</div>';

    const res  = await fetch('/admin/media/list?page=' + page, { credentials: 'same-origin' });
    const data = await res.json();

    if (data.items.length === 0) {
        grid.innerHTML = '<div class="col-12 text-center text-muted py-4">업로드된 미디어가 없습니다.</div>';
    } else {
        grid.innerHTML = data.items.map(item => (
            '<div class="col-4 col-md-3">' +
                '<div class="card border-0 shadow-sm h-100" role="button" title="' + escapeHtml(item.name) + '" onclick="selectMediaPickerItem(\'' + item.path + '\')">' +
                    '<div class="ratio ratio-1x1">' +
                        '<img src="/' + item.path + '" class="img-fluid object-fit-cover rounded" alt="' + escapeHtml(item.alt) + '">' +
                    '</div>' +
                '</div>' +
            '</div>'
        )).join('');
    }

    const pagination = document.getElementById('mediaPickerPagination');
    if (data.totalPages <= 1) {
        pagination.innerHTML = '';
    } else {
        let html = '';
        for (let p = 1; p <= data.totalPages; p++) {
            html += '<li class="page-item ' + (p === data.currentPage ? 'active' : '') + '">' +
                '<a class="page-link" href="#" onclick="event.preventDefault(); loadMediaPickerList(' + p + ')">' + p + '</a></li>';
        }
        pagination.innerHTML = html;
    }
}

async function uploadSettingImage(inputEl, key) {
    const file = inputEl.files[0];
    if (! file) {
        return;
    }

    const fd = new FormData();
    fd.append('file', file);

    const res  = await fetch('/admin/media/upload', { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();

    if (data.success) {
        applyImageToField(key, data.path.replace(/^\//, ''));
    } else {
        alert(data.error);
    }
    inputEl.value = '';
}
</script>
<?= $this->endSection() ?>
