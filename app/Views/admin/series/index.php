<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '시리즈 관리' ?>
<?= $this->section('content') ?>

<div class="alert alert-info small">
    시리즈는 운영자가 순서를 정하는 연재 묶음입니다. 한 글은 여러 시리즈에 속할 수 있으며, 글쓰기 화면에서 지정하거나, 시리즈 수정 화면에서 글을 검색·글 번호로 넣을 수 있습니다.
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

        <?php if ($editing): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <h2 class="h6 fw-semibold mb-3">시리즈에 글 넣기</h2>

                <form method="get" action="/admin/series" class="mb-3" role="search">
                    <input type="hidden" name="edit" value="<?= (int) $editing['id'] ?>">
                    <label class="form-label small fw-semibold" for="series-search">글 검색 (제목 또는 글 번호)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="q" id="series-search" class="form-control" value="<?= esc($keyword, 'attr') ?>">
                        <button type="submit" class="btn btn-outline-secondary">검색</button>
                    </div>
                </form>
                <?php if ($keyword !== ''): ?>
                    <?php if ($found === []): ?>
                        <p class="text-muted small">검색 결과가 없습니다.</p>
                    <?php else: ?>
                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach ($found as $post): ?>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                            <span class="small">
                                #<?= (int) $post['id'] ?> <?= esc($post['title']) ?>
                                <span class="text-muted">· <?= esc((string) $post['board_title']) ?></span>
                                <?php if ((int) $post['is_member'] === 1): ?>
                                    <span class="badge text-bg-secondary">이미 포함</span>
                                <?php endif; ?>
                            </span>
                            <?php if ((int) $post['is_member'] !== 1): ?>
                            <form method="post" action="/admin/series/<?= (int) $editing['id'] ?>/posts">
                                <?= csrf_field() ?>
                                <input type="hidden" name="post_ids" value="<?= (int) $post['id'] ?>">
                                <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap">추가</button>
                            </form>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                <?php endif; ?>

                <form method="post" action="/admin/series/<?= (int) $editing['id'] ?>/posts" class="mb-4">
                    <?= csrf_field() ?>
                    <label class="form-label small fw-semibold" for="series-post-ids">글 번호로 한 번에 넣기</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="post_ids" id="series-post-ids" class="form-control" placeholder="예: 12, 15, 20"
                               aria-describedby="series-post-ids-help" required>
                        <button type="submit" class="btn btn-primary">넣기</button>
                    </div>
                    <div id="series-post-ids-help" class="form-text">콤마(,)로 구분합니다. 입력한 순서대로 시리즈 맨 뒤에 붙습니다. 글은 여러 시리즈에 동시에 속할 수 있습니다.</div>
                </form>

                <h3 class="h6 fw-semibold">속한 글 (<?= count($members) ?>)</h3>
                <?php if ($members === []): ?>
                    <p class="text-muted small mb-0">아직 글이 없습니다.</p>
                <?php else: ?>
                <form method="post" action="/admin/series/<?= (int) $editing['id'] ?>/posts/order" id="series-order-form">
                    <?= csrf_field() ?>
                    <p class="form-text mt-0">손잡이(<i class="bi bi-grip-vertical" aria-hidden="true"></i>)를 끌어 순서를 바꾸고 "순서 저장"을 누르세요. 위쪽이 1편입니다.</p>
                    <ol class="list-group list-group-flush" id="series-members">
                        <?php foreach ($members as $post): ?>
                        <li class="list-group-item px-0 d-flex align-items-center gap-2">
                            <input type="hidden" name="post_ids[]" value="<?= (int) $post['id'] ?>">
                            <span class="series-handle text-muted" style="cursor:grab" title="끌어서 순서 변경"><i class="bi bi-grip-vertical" aria-hidden="true"></i><span class="visually-hidden">끌어서 순서 변경</span></span>
                            <span class="small flex-grow-1">
                                <span class="series-no fw-semibold"></span>
                                #<?= (int) $post['id'] ?> <?= esc($post['title']) ?>
                                <?php if (! (int) $post['is_active'] || (int) $post['is_secret']): ?>
                                    <span class="badge text-bg-light">비공개</span>
                                <?php endif; ?>
                            </span>
                            <button type="button" class="btn btn-outline-secondary btn-sm series-up" aria-label="위로">▲</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm series-down" aria-label="아래로">▼</button>
                            <button type="submit" class="btn btn-outline-danger btn-sm text-nowrap"
                                    formaction="/admin/series/<?= (int) $editing['id'] ?>/posts/<?= (int) $post['id'] ?>/remove">빼기</button>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-primary btn-sm px-4">순서 저장</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
<?php if ($editing && $members !== []): ?>
<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"
        integrity="sha384-BSxuMLxX+FCbTdYec3TbXlnMGEEM2QXTFdtDaveen71o+jswm2J36+xFqp8k4VHM"
        crossorigin="anonymous"></script>
<script>
(() => {
    const list = document.getElementById('series-members');
    const renumber = () => list.querySelectorAll('li').forEach((li, i) => {
        li.querySelector('.series-no').textContent = (i + 1) + '.';
    });
    renumber();
    new Sortable(list, { handle: '.series-handle', animation: 150, onEnd: renumber });
    list.addEventListener('click', (e) => {
        const btn = e.target.closest('.series-up, .series-down');
        if (!btn) return;
        const li = btn.closest('li');
        if (btn.classList.contains('series-up') && li.previousElementSibling) {
            list.insertBefore(li, li.previousElementSibling);
        } else if (btn.classList.contains('series-down') && li.nextElementSibling) {
            list.insertBefore(li.nextElementSibling, li);
        }
        renumber();
        btn.focus();
    });
})();
</script>
<?= $this->endSection() ?>
<?php endif; ?>
