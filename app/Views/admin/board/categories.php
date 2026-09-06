<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = ($board['name'] ?? '') . ' 카테고리 관리' ?>
<?= $this->section('content') ?>

<div class="mb-2">
    <a href="/admin/boards" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> 게시판 목록
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <strong><?= esc($board['name'] ?? '') ?> 카테고리</strong>
            </div>
            <table class="table table-hover board-table table-stack mb-0">
                <thead>
                    <tr>
                        <th scope="col" style="width:60px">순서</th>
                        <th scope="col">이름</th>
                        <th scope="col">슬러그</th>
                        <th scope="col" style="width:80px">상태</th>
                        <th scope="col"><span class="visually-hidden">관리</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted">등록된 카테고리가 없습니다.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($categories as $c): ?>
                    <tr>
                        <td data-label="순서"><?= $c['sort_order'] ?></td>
                        <td data-label="이름"><?= esc($c['name']) ?></td>
                        <td data-label="슬러그"><code><?= esc($c['slug']) ?></code></td>
                        <td data-label="상태"><?= $c['is_active'] ? '<span class="badge bg-success">활성</span>' : '<span class="badge bg-secondary">비활성</span>' ?></td>
                        <td data-label="" class="cell-actions">
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                    onclick="fillCategoryForm(<?= htmlspecialchars(json_encode($c)) ?>)">수정</button>
                            <form method="post" action="/admin/boards/<?= $board['id'] ?>/categories/<?= $c['id'] ?>/delete"
                                  class="d-inline" onsubmit="return confirm('삭제?')">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline-danger btn-sm">삭제</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong id="categoryFormTitle">카테고리 추가</strong></div>
            <div class="card-body">
                <?php if (session()->has('errors')): ?>
                <div class="alert alert-danger">
                    <?php foreach (session('errors') as $e): ?><div><?= esc($e) ?></div><?php endforeach; ?>
                </div>
                <?php endif; ?>
                <form method="post" id="categoryForm" action="/admin/boards/<?= $board['id'] ?>/categories">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label for="cName" class="form-label small">이름 *</label>
                        <input type="text" name="name" id="cName" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label for="cSlug" class="form-label small">슬러그 (영문, -, _) *</label>
                        <input type="text" name="slug" id="cSlug" class="form-control form-control-sm" required placeholder="예: notice, daily">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col">
                            <label for="cSort" class="form-label small">순서</label>
                            <input type="number" name="sort_order" id="cSort" class="form-control form-control-sm" value="0">
                        </div>
                        <div class="col d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input type="checkbox" name="is_active" value="1" id="cActive" class="form-check-input" checked>
                                <label for="cActive" class="form-check-label small">활성화</label>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">저장</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetCategoryForm()">초기화</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
const BOARD_ID = <?= (int) $board['id'] ?>;

function fillCategoryForm(c) {
    document.getElementById('categoryFormTitle').textContent = '카테고리 수정';
    document.getElementById('categoryForm').action = '/admin/boards/' + BOARD_ID + '/categories/' + c.id + '/edit';
    document.getElementById('cName').value   = c.name;
    document.getElementById('cSlug').value   = c.slug;
    document.getElementById('cSort').value   = c.sort_order;
    document.getElementById('cActive').checked = c.is_active == 1;
}
function resetCategoryForm() {
    document.getElementById('categoryFormTitle').textContent = '카테고리 추가';
    document.getElementById('categoryForm').action = '/admin/boards/' + BOARD_ID + '/categories';
    document.getElementById('categoryForm').reset();
}
</script>
<?= $this->endSection() ?>
