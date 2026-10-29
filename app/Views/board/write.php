<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container py-4">
<div class="mb-2">
    <a href="/board/<?= esc($board['slug']) ?>" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= esc($board['name']) ?> 목록
    </a>
</div>

<div class="card">
    <div class="card-header bg-white">
        <h1 class="h6 mb-0"><?= $post ? '게시글 수정' : '글쓰기' ?></h1>
    </div>
    <div class="card-body">
        <?php if (session()->has('errors')): ?>
        <div class="alert alert-danger" role="alert">
            <?php foreach (session('errors') as $err): ?><div><?= esc($err) ?></div><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="post"
              action="<?= $post ? "/board/{$board['slug']}/{$post['id']}/edit" : "/board/{$board['slug']}/write" ?>"
              enctype="multipart/form-data">
            <?= csrf_field() ?>

            <!-- 비회원 입력 -->
            <?php if (! session()->get('user_id')): ?>
            <div class="row g-2 mb-3">
                <div class="col-sm-3">
                    <label class="form-label small" for="author-name">
                        이름 <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(필수)</span>
                    </label>
                    <input type="text" name="author_name" id="author-name" class="form-control form-control-sm"
                           value="<?= old('author_name') ?>" required autocomplete="name">
                </div>
                <div class="col-sm-3">
                    <label class="form-label small" for="author-password">
                        비밀번호 <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(필수)</span>
                    </label>
                    <input type="password" name="author_password" id="author-password" class="form-control form-control-sm"
                           required autocomplete="new-password" aria-describedby="author-password-help">
                    <div class="form-text" id="author-password-help">수정·삭제할 때 필요합니다.</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 관리자 옵션 -->
            <?php if (session()->get('user_role') === 'admin'): ?>
            <div class="d-flex gap-3 mb-3">
                <div class="form-check">
                    <input type="checkbox" name="is_notice" value="1" id="is_notice" class="form-check-input"
                           <?= ($post && $post['is_notice']) ? 'checked' : '' ?>>
                    <label for="is_notice" class="form-check-label small">공지글로 등록</label>
                </div>
                <div>
                    <label for="publish_at" class="form-label small mb-1">예약 발행</label>
                    <input type="datetime-local" name="publish_at" id="publish_at" class="form-control form-control-sm" style="width:15rem"
                           aria-describedby="publish-at-help"
                           value="<?= esc(old('publish_at', ($post && $post['publish_at']) ? date('Y-m-d\TH:i', strtotime($post['publish_at'])) : '')) ?>">
                    <div id="publish-at-help" class="form-text">미래 시각을 지정하면 숨김으로 저장되고 그 시각 이후 자동 공개됩니다. 비우면 바로 공개합니다.</div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (! empty($categories)): ?>
            <div class="mb-3">
                <label class="form-label small" for="post-category">카테고리</label>
                <select name="category_id" id="post-category" class="form-select form-select-sm">
                    <option value="">선택 안 함</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= (int) ($post['category_id'] ?? $selectedCategoryId ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>><?= esc($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if (! empty($seriesList)): ?>
            <?php $checkedSeries = array_map('intval', (array) (old('series_ids') ?? array_keys($postSeries))); ?>
            <fieldset class="mb-3">
                <input type="hidden" name="series_form" value="1">
                <legend class="form-label small">
                    시리즈 <span class="text-muted">(여러 개 선택 가능)</span>
                    <button type="button" id="series-suggest-btn" class="btn btn-outline-secondary btn-sm ms-2 py-0">
                        <i class="bi bi-stars" aria-hidden="true"></i> AI 추천
                    </button>
                    <span id="series-suggest-status" class="small text-muted ms-2" role="status" aria-live="polite"></span>
                </legend>
                <?php foreach ($seriesList as $item): ?>
                <?php $sid = (int) $item['id']; ?>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <div class="form-check flex-grow-1">
                        <input type="checkbox" name="series_ids[]" value="<?= $sid ?>" id="post-series-<?= $sid ?>" class="form-check-input"
                               <?= in_array($sid, $checkedSeries, true) ? 'checked' : '' ?>>
                        <label for="post-series-<?= $sid ?>" class="form-check-label small"><?= esc($item['title']) ?></label>
                    </div>
                    <label class="visually-hidden" for="post-series-order-<?= $sid ?>"><?= esc($item['title']) ?> 순서</label>
                    <input type="number" name="series_order[<?= $sid ?>]" id="post-series-order-<?= $sid ?>" class="form-control form-control-sm" style="width:6rem"
                           min="1" max="65535" placeholder="순서"
                           value="<?= esc(old('series_order.' . $sid, $postSeries[$sid] ?? '')) ?>">
                </div>
                <?php endforeach; ?>
                <div class="form-text">순서를 비우면 해당 시리즈의 맨 뒤에 붙습니다.</div>
            </fieldset>
            <?php endif; ?>

            <div class="mb-3">
                <div class="form-check mb-2">
                    <input type="checkbox" name="is_secret" value="1" id="is_secret" class="form-check-input"
                           <?= ($post && $post['is_secret']) ? 'checked' : '' ?>>
                    <label for="is_secret" class="form-check-label small">비밀글</label>
                </div>
                <label class="form-label" for="post-title">
                    제목 <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(필수)</span>
                </label>
                <input type="text" name="title" id="post-title" class="form-control"
                       value="<?= esc(old('title', $post['title'] ?? '')) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label" for="post-tags">태그</label>
                <input type="text" name="tags" id="post-tags" class="form-control"
                       maxlength="500" aria-describedby="post-tags-help"
                       value="<?= esc(old('tags', $post['tags'] ?? '')) ?>">
                <div id="post-tags-help" class="form-text">쉼표(,)로 구분 · 최대 <?= \App\Models\PostModel::MAX_TAGS ?>개 · 로그인 후 공개글은 비워두면 본문으로 AI가 자동 추출</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="content-editor">내용</label>
                <textarea name="content" id="content-editor" class="form-control" rows="12"><?= old('content', $post['content'] ?? '') ?></textarea>
            </div>

            <?php if (! empty($signature)): ?>
            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="use_signature" value="1" id="use_signature" class="form-check-input"
                           <?= old('title') === null || old('use_signature') ? 'checked' : '' ?>>
                    <label for="use_signature" class="form-check-label small">글 하단에 서명 넣기 (<?= esc($signature['name']) ?>)</label>
                </div>
            </div>
            <?php endif; ?>

            <!-- 기존 파일 목록 (수정 시) -->
            <?php if (! empty($files)): ?>
            <fieldset class="mb-3">
                <legend class="form-label small text-muted">기존 첨부파일</legend>
                <?php foreach ($files as $file): ?>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <input type="checkbox" name="delete_files[]" value="<?= $file['id'] ?>" id="del_<?= $file['id'] ?>">
                    <label for="del_<?= $file['id'] ?>" class="small mb-0">
                        <?= $file['is_image'] ? '<i class="bi bi-image" aria-hidden="true"></i>' : '<i class="bi bi-file-earmark" aria-hidden="true"></i>' ?>
                        <?= esc($file['original_name']) ?>
                        <span class="text-danger small">(체크 시 삭제)</span>
                    </label>
                </div>
                <?php endforeach; ?>
            </fieldset>
            <?php endif; ?>

            <!-- 파일 첨부 -->
            <?php
            $imageExts   = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $fileExts    = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'txt', 'hwp'];
            $allowedExts = array_merge(
                $board['allow_image'] ? $imageExts : [],
                $board['allow_file']  ? $fileExts  : []
            );
            ?>
            <?php if ($allowedExts): ?>
            <div class="mb-3">
                <label class="form-label small" for="attachments">파일 첨부</label>
                <input type="file" name="attachments[]" id="attachments"
                       class="form-control form-control-sm" multiple
                       accept="<?= implode(',', array_map(fn($e) => '.' . $e, $allowedExts)) ?>">
                <div class="form-text text-muted">
                    허용 형식: <span class="fw-semibold"><?= strtoupper(implode(', ', $allowedExts)) ?></span>
                    &nbsp;·&nbsp; 최대 <span class="fw-semibold">10MB</span> / 복수 선택 가능
                </div>
                <div id="fileErrors" class="mt-1" role="alert" aria-live="assertive"></div>
                <div id="fileNames"  class="form-text text-muted mt-1" aria-live="polite"></div>
            </div>
            <?php endif; ?>

            <div class="d-flex gap-2 justify-content-end">
                <a href="/board/<?= esc($board['slug']) ?>" class="btn btn-outline-secondary btn-sm">취소</a>
                <button type="submit" class="btn btn-primary btn-sm"><?= $post ? '수정 완료' : '등록' ?></button>
            </div>
        </form>
    </div>
</div>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#content-editor',
    language: 'ko_KR',
    language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n@latest/langs6/ko_KR.js',
    height: 400,
    plugins: 'lists link image table code codesample',
    toolbar: 'undo redo | formatselect | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image | removeformat | codesample code',
    menubar: false,
    // 게시판 상세와 같은 CSS 를 써서 글자 크기·간격을 맞춘다 (body 에 .post-content 부여)
    content_css: '<?= asset_url('css/post-content.css') ?>',
    body_class: 'post-content',
    content_style: 'body { margin: 1rem; }',
    automatic_uploads: true,
    // 상대 경로로 저장하면 글 보는 주소(/index.php/...)에 따라 이미지 경로가 어긋난다 — 루트 기준 절대 경로로 저장
    relative_urls: false,
    remove_script_host: true,
    images_upload_handler(blobInfo) {
        return new Promise((resolve, reject) => {
            const fd = new FormData();
            fd.append('file', blobInfo.blob(), blobInfo.filename());
            fetch('/board/image-upload', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => data.location ? resolve(data.location) : reject(data.error ?? '업로드 실패'))
                .catch(() => reject('업로드 실패'));
        });
    },
});

document.querySelector('form').addEventListener('submit', async function (event) {
    event.preventDefault();

    try {
        await tinymce.activeEditor.uploadImages();
        tinymce.triggerSave();
        this.submit();
    } catch (error) {
        alert('이미지 업로드에 실패했습니다. 이미지를 다시 확인해 주세요.');
    }
});

// 시리즈 AI 추천 — 제목·본문을 보내 어울리는 시리즈를 체크해 준다 (저장은 운영자가 확인 후)
const seriesSuggestBtn = document.getElementById('series-suggest-btn');
if (seriesSuggestBtn) {
    const CSRF_FIELD_NAME = <?= json_encode(csrf_token()) ?>;
    let csrfHash = <?= json_encode(csrf_hash()) ?>;
    const statusBox = document.getElementById('series-suggest-status');

    seriesSuggestBtn.addEventListener('click', async function () {
        const editor = tinymce.activeEditor;
        const body = new URLSearchParams();
        body.set(CSRF_FIELD_NAME, csrfHash);
        body.set('title', document.getElementById('post-title').value);
        body.set('content', editor ? editor.getContent() : document.getElementById('content-editor').value);

        seriesSuggestBtn.disabled = true;
        statusBox.textContent = '추천 중…';

        try {
            const response = await fetch('/board/series-suggest', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: body.toString(),
            });
            const data = await response.json();

            if (data.csrf_hash) {
                csrfHash = data.csrf_hash;
                document.querySelectorAll('input[name="' + CSRF_FIELD_NAME + '"]').forEach(input => { input.value = csrfHash; });
            }
            if (!response.ok || !data.success) {
                throw new Error(data.message || '추천에 실패했습니다.');
            }

            const names = [];
            data.ids.forEach(id => {
                const box = document.getElementById('post-series-' + id);
                if (box) {
                    box.checked = true;
                    names.push(document.querySelector('label[for="post-series-' + id + '"]').textContent.trim());
                }
            });
            statusBox.textContent = names.length
                ? '추천: ' + names.join(', ') + ' — 확인 후 저장하세요.'
                : '어울리는 시리즈를 찾지 못했습니다.';
        } catch (error) {
            statusBox.textContent = error.message || '추천에 실패했습니다.';
        } finally {
            seriesSuggestBtn.disabled = false;
        }
    });
}

// 파일 선택 시 클라이언트 사전 검증
const attachInput = document.getElementById('attachments');
if (attachInput) {
    const ALLOWED = <?= json_encode($allowedExts) ?>;
    const MAX_BYTES = 10 * 1024 * 1024;

    attachInput.addEventListener('change', function () {
        const errors = [];
        const names  = [];

        Array.from(this.files).forEach(file => {
            const ext = file.name.split('.').pop().toLowerCase();
            if (! ALLOWED.includes(ext)) {
                errors.push(`${file.name}: 허용되지 않는 형식 (.${ext})`);
            } else if (file.size > MAX_BYTES) {
                errors.push(`${file.name}: 파일 크기 초과 (${(file.size/1024/1024).toFixed(1)}MB)`);
            } else {
                names.push(`${file.name} (${(file.size/1024).toFixed(0)}KB)`);
            }
        });

        const errBox  = document.getElementById('fileErrors');
        const nameBox = document.getElementById('fileNames');

        errBox.innerHTML = errors.map(e =>
            `<div class="text-danger small"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> ${e}</div>`
        ).join('');

        nameBox.textContent = names.length ? '선택된 파일: ' + names.join(' / ') : '';

        // 오류 있으면 input 초기화
        if (errors.length) this.value = '';
    });
}
</script>
<?= $this->endSection() ?>
