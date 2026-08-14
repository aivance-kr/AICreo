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
            </div>
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
                <label class="form-label" for="content-editor">내용</label>
                <textarea name="content" id="content-editor" class="form-control" rows="12"><?= old('content', $post['content'] ?? '') ?></textarea>
            </div>

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
<script src="https://cdn.tiny.cloud/1/<?= config('Editor')->tinymceApiKey ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#content-editor',
    language: 'ko_KR',
    height: 400,
    plugins: 'lists link image table code',
    toolbar: 'undo redo | formatselect | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image | removeformat | code',
    menubar: false,
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

document.querySelector('form').addEventListener('submit', function () {
    tinymce.triggerSave();
});

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
