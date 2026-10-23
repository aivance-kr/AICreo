<?= $this->extend('layouts/admin') ?>
<?php $pageTitle = '글 서명' ?>
<?= $this->section('content') ?>

<!-- 탭 -->
<ul class="nav nav-tabs mb-4">
    <?php foreach (['general' => '기본', 'contact' => '연락처', 'sns' => 'SNS', 'seo' => 'SEO', 'footer' => '푸터'] as $g => $label): ?>
    <li class="nav-item">
        <a class="nav-link" href="/admin/settings/<?= $g ?>"><?= $label ?></a>
    </li>
    <?php endforeach; ?>
    <li class="nav-item">
        <a class="nav-link" href="/admin/settings/oauth">소셜 로그인</a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="/admin/settings/signatures" aria-current="page">글 서명</a>
    </li>
</ul>

<div class="alert alert-info small">
    선택한 서명이 새 글 하단에 붙습니다. 서명을 바꿔도 이미 쓴 글은 바뀌지 않고, 글을 쓸 때 체크박스로 서명을 뺄 수 있습니다.
    본인 계정의 서명만 보이고 관리할 수 있습니다.
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <h2 class="h6 fw-semibold mb-3">내 서명 목록</h2>
        <?php if ($signatures === []): ?>
            <p class="text-muted small">등록된 서명이 없습니다.</p>
        <?php endif; ?>
        <?php foreach ($signatures as $sig): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold"><?= esc($sig['name']) ?></span>
                    <?php if ($sig['is_selected']): ?>
                        <span class="badge text-bg-primary">사용 중</span>
                    <?php endif; ?>
                </div>
                <div class="border rounded p-2 mb-2 small post-content"><?= post_content_html($sig['content']) ?></div>
                <div class="d-flex gap-2">
                    <?php if (! $sig['is_selected']): ?>
                    <form method="post" action="/admin/settings/signatures/<?= (int) $sig['id'] ?>/select">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-primary btn-sm">사용</button>
                    </form>
                    <?php endif; ?>
                    <a href="/admin/settings/signatures?edit=<?= (int) $sig['id'] ?>" class="btn btn-outline-secondary btn-sm">수정</a>
                    <form method="post" action="/admin/settings/signatures/<?= (int) $sig['id'] ?>/delete"
                          onsubmit="return confirm('이 서명을 삭제할까요? 이미 쓴 글의 서명은 그대로 남습니다.')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-danger btn-sm">삭제</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h6 fw-semibold mb-3"><?= $editing ? '서명 수정' : '새 서명 추가' ?></h2>
                <form method="post" action="/admin/settings/signatures<?= $editing ? '/' . (int) $editing['id'] : '' ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="sig-name">이름</label>
                        <input type="text" name="name" id="sig-name" class="form-control form-control-sm" maxlength="100"
                               value="<?= esc(old('name', $editing['name'] ?? '')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="sig-content">내용</label>
                        <textarea name="content" id="sig-content" class="form-control" rows="8"><?= esc(old('content', $editing['content'] ?? '')) ?></textarea>
                        <div class="form-text">HTML 입력 가능. 이미지는 에디터에서 업로드하면 미디어에 저장됩니다.</div>
                    </div>
                    <div class="text-end">
                        <?php if ($editing): ?>
                            <a href="/admin/settings/signatures" class="btn btn-outline-secondary btn-sm">취소</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-sm px-4"><?= $editing ? '수정' : '추가' ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#sig-content',
    language: 'ko_KR',
    language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n@latest/langs6/ko_KR.js',
    height: 300,
    plugins: 'lists link image code',
    toolbar: 'undo redo | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image | removeformat | code',
    menubar: false,
    automatic_uploads: true,
    relative_urls: false,
    remove_script_host: true,
    images_upload_handler(blobInfo) {
        return new Promise((resolve, reject) => {
            const fd = new FormData();
            fd.append('file', blobInfo.blob(), blobInfo.filename());
            fetch('/admin/media/upload', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => data.success ? resolve('/' + String(data.path).replace(/^\//, '')) : reject(data.error ?? '업로드 실패'))
                .catch(() => reject('업로드 실패'));
        });
    },
});
</script>
<?= $this->endSection() ?>
