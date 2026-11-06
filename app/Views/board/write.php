<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container py-4">
<div class="mb-2">
    <a href="/board/<?= esc($board['slug']) ?><?= esc($listQs, 'attr') ?>" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= esc(lang('Board.backToList', [$board['name']])) ?>
    </a>
</div>

<div class="card">
    <div class="card-header bg-white d-flex align-items-center">
        <h1 class="h6 mb-0"><?= esc(lang($post ? 'Board.editPost' : 'Board.writePost')) ?></h1>
        <?php if ($post && session()->get('user_role') === 'admin'): ?>
        <span class="ms-auto d-flex gap-3">
            <a href="/admin/posts/<?= (int) $post['id'] ?>/preview-links" class="small text-decoration-none">
                <i class="bi bi-link-45deg" aria-hidden="true"></i> <?= esc(lang('Board.previewLinks')) ?>
            </a>
            <a href="/admin/posts/<?= (int) $post['id'] ?>/revisions" class="small text-decoration-none">
                <i class="bi bi-clock-history" aria-hidden="true"></i> <?= esc(lang('Board.revisions')) ?>
            </a>
        </span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (session()->has('errors')): ?>
        <div class="alert alert-danger" role="alert">
            <?php foreach (session('errors') as $err): ?><div><?= esc($err) ?></div><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?= view('admin/partials/translations', ['translationRows' => $translationRows ?? []]) ?>

        <form method="post"
              action="<?= $post ? "/board/{$board['slug']}/{$post['id']}/edit" . esc($listQs, 'attr') : "/board/{$board['slug']}/write" ?>"
              enctype="multipart/form-data"
              data-autosave-key="post-draft:<?= esc($board['slug'], 'attr') ?>:<?= $post ? (int) $post['id'] : 'new' ?>"
              data-autosave-edit="<?= $post ? '1' : '0' ?>">
            <?= csrf_field() ?>

            <div id="autosave-notice" class="alert alert-warning small py-2" role="status" hidden></div>

            <!-- 비회원 입력 -->
            <?php if (! session()->get('user_id')): ?>
            <div class="row g-2 mb-3">
                <div class="col-sm-3">
                    <label class="form-label small" for="author-name">
                        <?= esc(lang('Board.name')) ?> <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"><?= esc(lang('Board.required')) ?></span>
                    </label>
                    <input type="text" name="author_name" id="author-name" class="form-control form-control-sm"
                           value="<?= old('author_name') ?>" required autocomplete="name">
                </div>
                <div class="col-sm-3">
                    <label class="form-label small" for="author-password">
                        <?= esc(lang('Board.password')) ?> <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"><?= esc(lang('Board.required')) ?></span>
                    </label>
                    <input type="password" name="author_password" id="author-password" class="form-control form-control-sm"
                           required autocomplete="new-password" aria-describedby="author-password-help">
                    <div class="form-text" id="author-password-help"><?= esc(lang('Board.passwordHelp')) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 관리자 옵션 -->
            <?php if (session()->get('user_role') === 'admin'): ?>
            <div class="d-flex gap-3 mb-3">
                <div class="form-check">
                    <input type="checkbox" name="is_notice" value="1" id="is_notice" class="form-check-input"
                           <?= ($post && $post['is_notice']) ? 'checked' : '' ?>>
                    <label for="is_notice" class="form-check-label small"><?= esc(lang('Board.registerNotice')) ?></label>
                </div>
                <div>
                    <label for="publish_at" class="form-label small mb-1"><?= esc(lang('Board.schedule')) ?></label>
                    <input type="datetime-local" name="publish_at" id="publish_at" class="form-control form-control-sm" style="width:15rem"
                           aria-describedby="publish-at-help"
                           value="<?= esc(old('publish_at', ($post && $post['publish_at']) ? date('Y-m-d\TH:i', strtotime($post['publish_at'])) : '')) ?>">
                    <div id="publish-at-help" class="form-text"><?= esc(lang('Board.scheduleHelp')) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (! empty($categories)): ?>
            <div class="mb-3">
                <label class="form-label small" for="post-category"><?= esc(lang('Board.categoryField')) ?></label>
                <select name="category_id" id="post-category" class="form-select form-select-sm">
                    <option value=""><?= esc(lang('Board.noCategory')) ?></option>
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
                    <?= esc(lang('Board.series')) ?> <span class="text-muted"><?= esc(lang('Board.multiSelect')) ?></span>
                    <button type="button" id="series-suggest-btn" class="btn btn-outline-secondary btn-sm ms-2 py-0">
                        <i class="bi bi-stars" aria-hidden="true"></i> <?= esc(lang('Board.aiRecommend')) ?>
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
                    <label class="visually-hidden" for="post-series-order-<?= $sid ?>"><?= esc(lang('Board.seriesOrderLabel', [$item['title']])) ?></label>
                    <input type="number" name="series_order[<?= $sid ?>]" id="post-series-order-<?= $sid ?>" class="form-control form-control-sm" style="width:6rem"
                           min="1" max="65535" placeholder="<?= esc(lang('Board.order'), 'attr') ?>"
                           value="<?= esc(old('series_order.' . $sid, $postSeries[$sid] ?? '')) ?>">
                </div>
                <?php endforeach; ?>
                <div class="form-text"><?= esc(lang('Board.orderHelp')) ?></div>
            </fieldset>
            <?php endif; ?>

            <div class="mb-3">
                <div class="form-check mb-2">
                    <input type="checkbox" name="is_secret" value="1" id="is_secret" class="form-check-input"
                           <?= ($post && $post['is_secret']) ? 'checked' : '' ?>>
                    <label for="is_secret" class="form-check-label small"><?= esc(lang('Board.secret')) ?></label>
                </div>
                <label class="form-label" for="post-title">
                    <?= esc(lang('Board.title')) ?> <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"><?= esc(lang('Board.required')) ?></span>
                </label>
                <input type="text" name="title" id="post-title" class="form-control"
                       value="<?= esc(old('title', $post['title'] ?? '')) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label" for="post-tags"><?= esc(lang('Board.tag')) ?></label>
                <input type="text" name="tags" id="post-tags" class="form-control"
                       maxlength="500" aria-describedby="post-tags-help"
                       value="<?= esc(old('tags', $post['tags'] ?? '')) ?>">
                <div id="post-tags-help" class="form-text"><?= esc(lang('Board.tagsHelp', [\App\Models\PostModel::MAX_TAGS])) ?></div>
            </div>

            <?php if (session()->get('user_role') === 'admin'): ?>
            <div class="mb-3">
                <label class="form-label" for="post-meta-desc"><?= esc(lang('Board.metaDesc')) ?></label>
                <textarea name="meta_desc" id="post-meta-desc" class="form-control" rows="2" maxlength="300"
                          aria-describedby="post-meta-desc-help"><?= esc(old('meta_desc', $post['meta_desc'] ?? '')) ?></textarea>
                <div id="post-meta-desc-help" class="form-text">
                    <?= esc(lang('Board.metaDescHelp')) ?>
                    <button type="button" id="ai-suggest-btn" class="btn btn-outline-secondary btn-sm ms-2 py-0">
                        <i class="bi bi-stars" aria-hidden="true"></i> <?= esc(lang('Board.aiSuggest')) ?>
                    </button>
                    <span id="ai-suggest-status" class="small text-muted ms-2" role="status" aria-live="polite"></span>
                </div>
                <div id="ai-suggest-result" class="border rounded p-2 mt-2 small" hidden></div>
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label" for="content-editor"><?= esc(lang('Board.content')) ?></label>
                <textarea name="content" id="content-editor" class="form-control" rows="12"><?= old('content', $post['content'] ?? '') ?></textarea>
            </div>

            <?php if (! empty($signature)): ?>
            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="use_signature" value="1" id="use_signature" class="form-check-input"
                           <?= old('title') === null || old('use_signature') ? 'checked' : '' ?>>
                    <label for="use_signature" class="form-check-label small"><?= esc(lang('Board.useSignature', [$signature['name']])) ?></label>
                </div>
            </div>
            <?php endif; ?>

            <!-- 기존 파일 목록 (수정 시) -->
            <?php if (! empty($files)): ?>
            <fieldset class="mb-3">
                <legend class="form-label small text-muted"><?= esc(lang('Board.existingFiles')) ?></legend>
                <?php foreach ($files as $file): ?>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <input type="checkbox" name="delete_files[]" value="<?= $file['id'] ?>" id="del_<?= $file['id'] ?>">
                    <label for="del_<?= $file['id'] ?>" class="small mb-0">
                        <?= $file['is_image'] ? '<i class="bi bi-image" aria-hidden="true"></i>' : '<i class="bi bi-file-earmark" aria-hidden="true"></i>' ?>
                        <?= esc($file['original_name']) ?>
                        <span class="text-danger small"><?= esc(lang('Board.checkToDelete')) ?></span>
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
                <label class="form-label small" for="attachments"><?= esc(lang('Board.attachFile')) ?></label>
                <input type="file" name="attachments[]" id="attachments"
                       class="form-control form-control-sm" multiple
                       accept="<?= implode(',', array_map(fn($e) => '.' . $e, $allowedExts)) ?>">
                <div class="form-text text-muted">
                    <?= esc(lang('Board.allowedFormats')) ?> <span class="fw-semibold"><?= strtoupper(implode(', ', $allowedExts)) ?></span>
                    &nbsp;·&nbsp; <?= lang('Board.maxSizeMulti', ['<span class="fw-semibold">10MB</span>']) ?>
                </div>
                <div id="fileErrors" class="mt-1" role="alert" aria-live="assertive"></div>
                <div id="fileNames"  class="form-text text-muted mt-1" aria-live="polite"></div>
            </div>
            <?php endif; ?>

            <div class="d-flex gap-2 justify-content-end">
                <a href="/board/<?= esc($board['slug']) ?><?= esc($listQs, 'attr') ?>" class="btn btn-outline-secondary btn-sm"><?= esc(lang('Board.cancel')) ?></a>
                <button type="submit" class="btn btn-primary btn-sm"><?= esc(lang($post ? 'Board.editDone' : 'Board.register')) ?></button>
            </div>
        </form>
    </div>
</div>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
<script src="<?= asset_url('js/post-autosave.js') ?>"></script>
<script>
let autosave = null;

tinymce.init({
    selector: '#content-editor',
    language: 'ko_KR',
    language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n@latest/langs6/ko_KR.js',
    height: 400,
    plugins: 'lists link image table code codesample',
    toolbar: 'undo redo | blocks fontsize | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist outdent indent | blockquote hr | link image table | removeformat | codesample code',
    toolbar_mode: 'wrap',
    blocks_formats: '문단=p; 제목 2=h2; 제목 3=h3; 제목 4=h4; 인용=blockquote; 코드 블록=pre',
    font_size_formats: '12px 14px 16px 18px 20px 24px 28px 32px',
    menubar: 'edit insert format table',
    // 게시판 상세와 같은 CSS 를 써서 글자 크기·간격을 맞춘다 (body 에 .post-content 부여)
    content_css: '<?= asset_url('css/post-content.css') ?>',
    body_class: 'post-content',
    content_style: 'body { margin: 1rem; }',
    automatic_uploads: true,
    // 에디터가 준비되면 임시저장을 시작한다 (본문은 에디터가 정규화한 값을 기준으로 비교)
    init_instance_callback(editor) {
        const form = document.querySelector('form[data-autosave-key]');
        if (!form || typeof PostAutosave !== 'function') {
            return;
        }
        autosave = PostAutosave({
            form,
            notice: document.getElementById('autosave-notice'),
            key: form.dataset.autosaveKey,
            isEdit: form.dataset.autosaveEdit === '1',
            getContent: () => editor.getContent(),
            setContent: (html) => editor.setContent(html),
        });
    },
    // 상대 경로로 저장하면 글 보는 주소(/index.php/...)에 따라 이미지 경로가 어긋난다 — 루트 기준 절대 경로로 저장
    relative_urls: false,
    remove_script_host: true,
    images_upload_handler(blobInfo) {
        return new Promise((resolve, reject) => {
            const fd = new FormData();
            fd.append('file', blobInfo.blob(), blobInfo.filename());
            fetch('/board/image-upload', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => data.location ? resolve(data.location) : reject(data.error ?? <?= json_encode(lang('Board.uploadFailed'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>))
                .catch(() => reject(<?= json_encode(lang('Board.uploadFailed'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>));
        });
    },
});

document.querySelector('form').addEventListener('submit', async function (event) {
    event.preventDefault();

    try {
        await tinymce.activeEditor.uploadImages();
        tinymce.triggerSave();
        if (autosave) autosave.discard();
        this.submit();
    } catch (error) {
        alert(<?= json_encode(lang('Board.imageUploadFailed'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>);
    }
});

// AJAX 요청 공통 — CSRF 토큰은 요청마다 재발급되므로 폼의 hidden 값을 단일 출처로 쓴다 (버튼마다 따로 들고 있으면 낡은 토큰으로 403 HTML 이 온다)
const CSRF_FIELD_NAME = <?= json_encode(csrf_token()) ?>;
const currentCsrfHash = () => document.querySelector('input[name="' + CSRF_FIELD_NAME + '"]').value;
const applyCsrfHash = hash => {
    if (hash) {
        document.querySelectorAll('input[name="' + CSRF_FIELD_NAME + '"]').forEach(input => { input.value = hash; });
    }
};
// JSON 이 아닌 응답(만료된 CSRF·서버 오류의 HTML 페이지)은 파싱 오류 대신 안내 문구로 바꾼다
const readJson = async response => {
    try {
        return await response.json();
    } catch (error) {
        throw new Error(<?= json_encode(lang('Board.requestRejected'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>.replace('{0}', response.status));
    }
};

// 시리즈 AI 추천 — 제목·본문을 보내 어울리는 시리즈를 체크해 준다 (저장은 운영자가 확인 후)
const seriesSuggestBtn = document.getElementById('series-suggest-btn');
if (seriesSuggestBtn) {
    const statusBox = document.getElementById('series-suggest-status');

    seriesSuggestBtn.addEventListener('click', async function () {
        const editor = tinymce.activeEditor;
        const body = new URLSearchParams();
        body.set(CSRF_FIELD_NAME, currentCsrfHash());
        body.set('title', document.getElementById('post-title').value);
        body.set('content', editor ? editor.getContent() : document.getElementById('content-editor').value);

        seriesSuggestBtn.disabled = true;
        statusBox.textContent = <?= json_encode(lang('Board.suggesting'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

        try {
            const response = await fetch('/board/series-suggest', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: body.toString(),
            });
            const data = await readJson(response);

            applyCsrfHash(data.csrf_hash);
            if (!response.ok || !data.success) {
                throw new Error(data.message || <?= json_encode(lang('Board.suggestFailed'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>);
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
                ? <?= json_encode(lang('Board.suggestedSeries'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>.replace('{0}', names.join(', '))
                : <?= json_encode(lang('Board.noSeriesFound'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        } catch (error) {
            statusBox.textContent = error.message || <?= json_encode(lang('Board.suggestFailed'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        } finally {
            seriesSuggestBtn.disabled = false;
        }
    });
}

// 글 AI 제안 — 요약(메타 설명)·태그·제목 후보를 받아 보여 주고, 운영자가 "적용"을 눌러야 입력란에 채운다 (저장은 폼 저장 때)
const aiSuggestBtn = document.getElementById('ai-suggest-btn');
if (aiSuggestBtn) {
    const aiStatus = document.getElementById('ai-suggest-status');
    const aiResult = document.getElementById('ai-suggest-result');

    // 후보 한 줄 — 텍스트는 textContent 로만 넣어 XSS 를 막는다
    const addRow = (label, text, onApply) => {
        const row = document.createElement('div');
        row.className = 'd-flex align-items-start gap-2 mb-1';
        const name = document.createElement('strong');
        name.className = 'flex-shrink-0';
        name.textContent = label;
        const value = document.createElement('span');
        value.className = 'flex-grow-1';
        value.textContent = text;
        const apply = document.createElement('button');
        apply.type = 'button';
        apply.className = 'btn btn-outline-primary btn-sm py-0 flex-shrink-0';
        apply.textContent = <?= json_encode(lang('Board.apply'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        apply.addEventListener('click', onApply);
        row.append(name, value, apply);
        aiResult.appendChild(row);
    };

    aiSuggestBtn.addEventListener('click', async function () {
        const editor = tinymce.activeEditor;
        const body = new URLSearchParams();
        body.set(CSRF_FIELD_NAME, currentCsrfHash());
        body.set('title', document.getElementById('post-title').value);
        body.set('content', editor ? editor.getContent() : document.getElementById('content-editor').value);

        aiSuggestBtn.disabled = true;
        aiStatus.textContent = <?= json_encode(lang('Board.suggestingPost'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        aiResult.hidden = true;
        aiResult.replaceChildren();

        try {
            const response = await fetch('/board/ai-suggest', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: body.toString(),
            });
            const data = await readJson(response);

            applyCsrfHash(data.csrf_hash);
            if (!response.ok || !data.success) {
                throw new Error(data.message || <?= json_encode(lang('Board.suggestPostFailed'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>);
            }

            const suggestion = data.suggestion;
            if (suggestion.summary) {
                addRow(<?= json_encode(lang('Board.summary'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>, suggestion.summary, () => { document.getElementById('post-meta-desc').value = suggestion.summary; });
            }
            if (suggestion.tags.length) {
                addRow(<?= json_encode(lang('Board.tag'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>, suggestion.tags.join(', '), () => { document.getElementById('post-tags').value = suggestion.tags.join(','); });
            }
            suggestion.titles.forEach((title, index) => {
                addRow(<?= json_encode(lang('Board.titleN'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>.replace('{0}', index + 1), title, () => { document.getElementById('post-title').value = title; });
            });
            aiResult.hidden = false;
            aiStatus.textContent = <?= json_encode(lang('Board.applyHint'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        } catch (error) {
            aiStatus.textContent = error.message || <?= json_encode(lang('Board.suggestPostFailed'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        } finally {
            aiSuggestBtn.disabled = false;
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
                errors.push(<?= json_encode(lang('Board.badFormat'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>.replace('{0}', file.name).replace('{1}', ext));
            } else if (file.size > MAX_BYTES) {
                errors.push(<?= json_encode(lang('Board.tooLarge'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>.replace('{0}', file.name).replace('{1}', (file.size/1024/1024).toFixed(1)));
            } else {
                names.push(`${file.name} (${(file.size/1024).toFixed(0)}KB)`);
            }
        });

        const errBox  = document.getElementById('fileErrors');
        const nameBox = document.getElementById('fileNames');

        errBox.innerHTML = errors.map(e =>
            `<div class="text-danger small"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> ${e}</div>`
        ).join('');

        nameBox.textContent = names.length ? <?= json_encode(lang('Board.selectedFiles'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>.replace('{0}', names.join(' / ')) : '';

        // 오류 있으면 input 초기화
        if (errors.length) this.value = '';
    });
}
</script>
<?= $this->endSection() ?>
