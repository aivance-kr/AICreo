<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '메뉴 관리' ?>
<?= $this->section('content') ?>

<div class="row g-4">
    <!-- 메뉴 목록 -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>현재 메뉴</strong>
                <span class="small text-muted"><i class="bi bi-arrows-move me-1" aria-hidden="true"></i>드래그해서 순서 변경</span>
            </div>
            <ul class="list-group list-group-flush" id="menuRoot">
                <?php foreach ($menuTree as $top): ?>
                <li class="list-group-item" data-id="<?= $top['id'] ?>">
                    <div class="d-flex align-items-start gap-2">
                        <span class="drag-handle text-muted" style="cursor:grab"><i class="bi bi-grip-vertical" aria-hidden="true"></i></span>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= esc($top['title']) ?></strong>
                                    <code class="small text-muted ms-1"><?= esc($top['url']) ?></code>
                                    <?= $top['is_active'] ? '' : '<span class="badge bg-secondary ms-1">비활성</span>' ?>
                                </div>
                                <div class="text-nowrap">
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            onclick="fillEditForm(<?= htmlspecialchars(json_encode($top)) ?>)">수정</button>
                                    <form method="post" action="/admin/menus/<?= $top['id'] ?>/delete" class="d-inline" onsubmit="return confirm('삭제?')">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-outline-danger btn-sm">삭제</button>
                                    </form>
                                </div>
                            </div>
                            <?php if ($top['children']): ?>
                            <ul class="list-group list-group-flush mt-2 menu-children" data-parent="<?= $top['id'] ?>">
                                <?php foreach ($top['children'] as $child): ?>
                                <li class="list-group-item py-2" data-id="<?= $child['id'] ?>">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="drag-handle text-muted" style="cursor:grab"><i class="bi bi-grip-vertical" aria-hidden="true"></i></span>
                                        <div class="flex-grow-1 d-flex justify-content-between align-items-center">
                                            <div>
                                                &nbsp;&nbsp;└ <?= esc($child['title']) ?>
                                                <code class="small text-muted ms-1"><?= esc($child['url']) ?></code>
                                                <?= $child['is_active'] ? '' : '<span class="badge bg-secondary ms-1">비활성</span>' ?>
                                            </div>
                                            <div class="text-nowrap">
                                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                                        onclick="fillEditForm(<?= htmlspecialchars(json_encode($child)) ?>)">수정</button>
                                                <form method="post" action="/admin/menus/<?= $child['id'] ?>/delete" class="d-inline" onsubmit="return confirm('삭제?')">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-outline-danger btn-sm">삭제</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- 추가/수정 폼 -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong id="formTitle">메뉴 추가</strong></div>
            <div class="card-body">
                <form method="post" id="menuForm" action="/admin/menus">
                    <?= csrf_field() ?>
                    <input type="hidden" id="menuId" name="_menu_id" value="">
                    <div class="mb-2">
                        <label for="mTitle" class="form-label small">제목 *</label>
                        <input type="text" name="title" id="mTitle" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label for="mUrl" class="form-label small">URL *</label>
                        <input type="text" name="url" id="mUrl" class="form-control form-control-sm" required placeholder="/about">
                    </div>
                    <div class="mb-2">
                        <label for="mParent" class="form-label small">상위 메뉴 ID (없으면 비워두세요)</label>
                        <input type="number" name="parent_id" id="mParent" class="form-control form-control-sm" placeholder="없음">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col">
                            <label for="mSort" class="form-label small">순서</label>
                            <input type="number" name="sort_order" id="mSort" class="form-control form-control-sm" value="0">
                        </div>
                        <div class="col">
                            <label for="mTarget" class="form-label small">링크 타겟</label>
                            <select name="target" id="mTarget" class="form-select form-select-sm">
                                <option value="_self">같은 창</option>
                                <option value="_blank">새 창</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" value="1" id="mActive" class="form-check-input" checked>
                        <label for="mActive" class="form-check-label small">활성화</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">저장</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetForm()">초기화</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"
        integrity="sha384-BSxuMLxX+FCbTdYec3TbXlnMGEEM2QXTFdtDaveen71o+jswm2J36+xFqp8k4VHM"
        crossorigin="anonymous"></script>
<script>
const CSRF_FIELD_NAME = <?= json_encode(csrf_token()) ?>;
let csrfHash = <?= json_encode(csrf_hash()) ?>;

// regenerate=true 라 드래그 성공마다 해시가 회전한다. 페이지에 이미 렌더링된
// 수정/삭제 폼의 csrf_field() 히든 인풋도 함께 갱신하지 않으면 다음 제출이 막힌다.
function syncCsrfHash(newHash) {
    if (!newHash) return;
    csrfHash = newHash;
    document.querySelectorAll('input[name="' + CSRF_FIELD_NAME + '"]').forEach(input => {
        input.value = newHash;
    });
}

async function saveMenuOrder() {
    const ids = [];
    document.querySelectorAll('#menuRoot > li[data-id]').forEach(li => {
        ids.push(li.dataset.id);
        const children = li.querySelector('.menu-children');
        if (children) {
            children.querySelectorAll('li[data-id]').forEach(c => ids.push(c.dataset.id));
        }
    });

    const body = new URLSearchParams();
    body.set(CSRF_FIELD_NAME, csrfHash);
    ids.forEach(id => body.append('ids[]', id));

    let res;
    try {
        res = await fetch('/admin/menus/reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString(),
        });
    } catch (e) {
        console.error('메뉴 순서 저장 요청 실패', e);
        alert('순서 저장에 실패했습니다. 새로고침 후 다시 시도해주세요.');
        return;
    }

    if (!res.ok) {
        console.error('메뉴 순서 저장 실패', res.status);
        alert('순서 저장에 실패했습니다. 새로고침 후 다시 시도해주세요.');
        return;
    }

    const data = await res.json();
    syncCsrfHash(data.csrf_hash);
}

new Sortable(document.getElementById('menuRoot'), {
    handle: '.drag-handle',
    animation: 150,
    onEnd: saveMenuOrder,
});
document.querySelectorAll('.menu-children').forEach(el => {
    new Sortable(el, { handle: '.drag-handle', animation: 150, onEnd: saveMenuOrder });
});

function fillEditForm(m) {
    document.getElementById('formTitle').textContent = '메뉴 수정';
    document.getElementById('menuForm').action = '/admin/menus/' + m.id + '/edit';
    document.getElementById('mTitle').value   = m.title;
    document.getElementById('mUrl').value     = m.url;
    document.getElementById('mParent').value  = m.parent_id || '';
    document.getElementById('mSort').value    = m.sort_order;
    document.getElementById('mTarget').value  = m.target;
    document.getElementById('mActive').checked = m.is_active == 1;
}
function resetForm() {
    document.getElementById('formTitle').textContent = '메뉴 추가';
    document.getElementById('menuForm').action = '/admin/menus';
    document.getElementById('menuForm').reset();
}
</script>
<?= $this->endSection() ?>
