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
                    <?php /* 업로드 실패를 브라우저 alert 대신 필드 옆에 남긴다 */ ?>
                    <div class="form-text text-danger" id="err_<?= esc($s['key']) ?>" role="alert"></div>
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
let mediaPickerTrigger = null; // 모달을 연 버튼 — 닫을 때 초점을 돌려준다

const mediaPickerModalEl = document.getElementById('mediaPickerModal');
const mediaPickerGrid    = document.getElementById('mediaPickerGrid');
const mediaPickerPager   = document.getElementById('mediaPickerPagination');

/** 요소를 만들어 돌려준다 — 문자열로 HTML 을 조립하지 않아 주입 표면이 없다 */
function el(tag, attrs = {}, text = null) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        node.setAttribute(k, v);
    }
    if (text !== null) {
        node.textContent = text;
    }

    return node;
}

function applyImageToField(key, path, label) {
    document.getElementById('input_' + key).value = path;

    document.getElementById('preview_' + key).replaceChildren(el('img', {
        src: '/' + path,
        style: 'max-height:60px',
        class: 'img-thumbnail',
        alt: label || '선택한 이미지',
    }));
}

function setFieldError(key, message) {
    const box = document.getElementById('err_' + key);
    if (box) {
        box.textContent = message ?? '';
    }
}

function openMediaPicker(key) {
    mediaPickerTargetKey = key;
    mediaPickerTrigger   = document.activeElement;

    if (! mediaPickerModalInstance) {
        mediaPickerModalInstance = new bootstrap.Modal(mediaPickerModalEl);
    }
    loadMediaPickerList(1);
    mediaPickerModalInstance.show();
}

// 프로그래밍 방식으로 연 모달은 Bootstrap 이 초점을 되돌려주지 않는다.
// 닫힐 때 열었던 버튼으로 직접 돌려보낸다 — 안 그러면 초점이 body 로 떨어진다.
mediaPickerModalEl.addEventListener('hidden.bs.modal', () => {
    if (mediaPickerTrigger && document.contains(mediaPickerTrigger)) {
        mediaPickerTrigger.focus();
    }
    mediaPickerTrigger = null;
});

// 그리드는 매번 다시 그려지므로 개별 요소가 아니라 컨테이너에서 위임 처리한다.
mediaPickerGrid.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-path]');
    if (! btn) {
        return;
    }
    applyImageToField(mediaPickerTargetKey, btn.dataset.path, btn.dataset.label);
    setFieldError(mediaPickerTargetKey, '');
    mediaPickerModalInstance.hide();
});

mediaPickerPager.addEventListener('click', (e) => {
    const link = e.target.closest('[data-page]');
    if (! link) {
        return;
    }
    e.preventDefault();
    loadMediaPickerList(Number(link.dataset.page), true);
});

function renderMediaPickerItems(items) {
    const frag = document.createDocumentFragment();

    for (const item of items) {
        const name = (item.alt && item.alt.trim()) ? item.alt : item.name;

        // role="button" 을 얹은 div 는 키보드로 도달·조작되지 않는다. 진짜 버튼을 쓴다.
        const btn = el('button', {
            type: 'button',
            class: 'btn p-0 border-0 card shadow-sm h-100 w-100',
            'data-path': item.path,
            'data-label': name,
            'aria-label': name + ' 선택',
        });

        const ratio = el('div', { class: 'ratio ratio-1x1' });
        ratio.appendChild(el('img', {
            src: '/' + item.path,
            class: 'img-fluid object-fit-cover rounded',
            alt: '', // 버튼이 이미 이름을 가지므로 중복해 읽지 않는다
        }));
        btn.appendChild(ratio);

        const col = el('div', { class: 'col-4 col-md-3' });
        col.appendChild(btn);
        frag.appendChild(col);
    }

    return frag;
}

function renderMediaPickerPagination(data, focusCurrent) {
    mediaPickerPager.replaceChildren();
    if (data.totalPages <= 1) {
        return;
    }

    for (let p = 1; p <= data.totalPages; p++) {
        const isCurrent = p === data.currentPage;
        const li = el('li', { class: 'page-item' + (isCurrent ? ' active' : '') });
        const a  = el('a', { class: 'page-link', href: '#', 'data-page': String(p) });

        if (isCurrent) {
            a.setAttribute('aria-current', 'page');
        }
        a.appendChild(el('span', { class: 'visually-hidden' }, '페이지 '));
        a.appendChild(document.createTextNode(String(p)));

        li.appendChild(a);
        mediaPickerPager.appendChild(li);
    }

    // 페이지를 눌러 다시 그렸다면 초점이 사라진 요소에 남는다. 새 현재 페이지로 옮긴다.
    if (focusCurrent) {
        mediaPickerPager.querySelector('[aria-current="page"]')?.focus();
    }
}

async function loadMediaPickerList(page, fromPager = false) {
    mediaPickerGrid.setAttribute('aria-busy', 'true');
    mediaPickerGrid.replaceChildren(
        el('div', { class: 'col-12 text-center text-muted py-4' }, '불러오는 중...')
    );

    try {
        const res  = await fetch('/admin/media/list?page=' + page, { credentials: 'same-origin' });
        const data = await res.json();

        if (data.items.length === 0) {
            mediaPickerGrid.replaceChildren(
                el('div', { class: 'col-12 text-center text-muted py-4' }, '업로드된 미디어가 없습니다.')
            );
        } else {
            mediaPickerGrid.replaceChildren(renderMediaPickerItems(data.items));
        }

        renderMediaPickerPagination(data, fromPager);
    } catch {
        mediaPickerGrid.replaceChildren(
            el('div', { class: 'col-12 text-center text-danger py-4' }, '목록을 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.')
        );
    } finally {
        mediaPickerGrid.setAttribute('aria-busy', 'false');
    }
}

async function uploadSettingImage(inputEl, key) {
    const file = inputEl.files[0];
    if (! file) {
        return;
    }

    setFieldError(key, '');

    const fd = new FormData();
    fd.append('file', file);

    try {
        const res  = await fetch('/admin/media/upload', { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await res.json();

        if (data.success) {
            applyImageToField(key, data.path.replace(/^\//, ''));
        } else {
            // 브라우저 alert 은 흐름을 막고 초점을 앗아간다. 필드 옆에 남긴다.
            setFieldError(key, data.error ?? '업로드에 실패했습니다.');
        }
    } catch {
        setFieldError(key, '업로드 중 문제가 발생했습니다. 잠시 후 다시 시도해 주세요.');
    } finally {
        inputEl.value = '';
    }
}
</script>
<?= $this->endSection() ?>
