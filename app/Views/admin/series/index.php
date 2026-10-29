<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '시리즈 관리' ?>
<?= $this->section('content') ?>

<div class="alert alert-info small">
    시리즈는 운영자가 순서를 정하는 연재 묶음입니다. 한 글은 하나의 시리즈에만 속하며, 글을 쓰거나 수정할 때 "시리즈"와 "순서"를 지정합니다.
    시리즈를 삭제해도 글은 지워지지 않고 소속만 해제됩니다.
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <h2 class="h6 fw-semibold mb-3">시리즈 목록</h2>
        <?php if ($seriesList === []): ?>
            <p class="text-muted small">등록된 시리즈가 없습니다.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <caption class="visually-hidden">시리즈 목록</caption>
                <thead>
                    <tr><th scope="col">제목</th><th scope="col">상태</th><th scope="col" class="text-end">글 수</th><th scope="col"><span class="visually-hidden">관리</span></th></tr>
                </thead>
                <tbody>
                <?php foreach ($seriesList as $item): ?>
                    <tr>
                        <td>
                            <a href="/series/<?= esc($item['slug'], 'attr') ?>" target="_blank" rel="noopener"><?= esc($item['title']) ?></a>
                            <div class="small text-muted">/series/<?= esc($item['slug']) ?></div>
                        </td>
                        <td><?= esc(\App\Models\SeriesModel::STATUSES[$item['status']] ?? $item['status']) ?></td>
                        <td class="text-end"><?= (int) $item['post_count'] ?></td>
                        <td class="text-end text-nowrap">
                            <a href="/admin/series?edit=<?= (int) $item['id'] ?>" class="btn btn-outline-secondary btn-sm">수정</a>
                            <form method="post" action="/admin/series/<?= (int) $item['id'] ?>/delete" class="d-inline"
                                  onsubmit="return confirm('이 시리즈를 삭제할까요? 속한 글은 남고 소속만 해제됩니다.')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline-danger btn-sm">삭제</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h6 fw-semibold mb-3"><?= $editing ? '시리즈 수정' : '새 시리즈 추가' ?></h2>
                <form method="post" action="/admin/series<?= $editing ? '/' . (int) $editing['id'] : '' ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="series-title">제목</label>
                        <input type="text" name="title" id="series-title" class="form-control form-control-sm" maxlength="200"
                               value="<?= esc(old('title', $editing['title'] ?? '')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="series-slug">주소(slug)</label>
                        <input type="text" name="slug" id="series-slug" class="form-control form-control-sm" maxlength="100"
                               pattern="[a-z0-9]+(-[a-z0-9]+)*" aria-describedby="series-slug-help"
                               value="<?= esc(old('slug', $editing['slug'] ?? '')) ?>" required>
                        <div id="series-slug-help" class="form-text">영문 소문자·숫자·하이픈. 소개 페이지 주소가 /series/주소 가 됩니다.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="series-status">상태</label>
                        <select name="status" id="series-status" class="form-select form-select-sm">
                            <?php foreach (\App\Models\SeriesModel::STATUSES as $value => $label): ?>
                            <option value="<?= esc($value, 'attr') ?>" <?= old('status', $editing['status'] ?? 'ongoing') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="series-desc">소개</label>
                        <textarea name="description" id="series-desc" class="form-control form-control-sm" rows="4" maxlength="2000"><?= esc(old('description', $editing['description'] ?? '')) ?></textarea>
                    </div>
                    <div class="text-end">
                        <?php if ($editing): ?>
                            <a href="/admin/series" class="btn btn-outline-secondary btn-sm">취소</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-sm px-4"><?= $editing ? '수정' : '추가' ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
