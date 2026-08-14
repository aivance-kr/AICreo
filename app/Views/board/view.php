<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container py-4">
<div class="mb-2">
    <a href="/board/<?= esc($board['slug']) ?>" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= esc($board['name']) ?> 목록
    </a>
</div>

<div class="card mb-4">
    <div class="card-header bg-white">
        <h1 class="h5 mb-1"><?= esc($post['title']) ?></h1>
        <div class="d-flex gap-3 text-muted small">
            <span><i class="bi bi-person" aria-hidden="true"></i><span class="visually-hidden">작성자 </span> <?= esc($post['user_nickname'] ?? mask_name($post['author_name'])) ?></span>
            <span><i class="bi bi-clock" aria-hidden="true"></i><span class="visually-hidden">작성일 </span> <?= $post['created_at'] ?></span>
            <span><i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">조회수 </span> <?= number_format($post['views']) ?></span>
        </div>
    </div>
    <div class="card-body">
        <div class="post-content">
            <?= $post['content'] ?>
        </div>

        <!-- 이미지 첨부 -->
        <?php $images = array_filter($files, fn($f) => $f['is_image']); ?>
        <?php if ($images): ?>
        <hr>
        <div class="row g-2 mt-2">
            <?php foreach ($images as $img): ?>
            <div class="col-auto">
                <a href="/<?= esc($img['file_path']) ?>" target="_blank" rel="noopener">
                    <img loading="lazy" src="/<?= esc($img['file_path']) ?>" alt="<?= esc($img['original_name']) ?>"
                         class="img-thumbnail" style="max-height:200px;">
                    <span class="visually-hidden">원본 이미지 보기 (새 창 열림)</span>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- 파일 첨부 -->
        <?php $attachments = array_filter($files, fn($f) => !$f['is_image']); ?>
        <?php if ($attachments): ?>
        <hr>
        <div class="file-list">
            <div class="text-muted small mb-1"><i class="bi bi-paperclip" aria-hidden="true"></i> 첨부파일</div>
            <?php foreach ($attachments as $file): ?>
            <div>
                <a href="/board/file/<?= $file['id'] ?>/download" class="text-decoration-none">
                    <i class="bi bi-file-earmark" aria-hidden="true"></i>
                    <?= esc($file['original_name']) ?>
                    <span class="text-muted">(<?= round($file['file_size'] / 1024) ?>KB)</span>
                    <span class="text-muted small">↓<?= $file['download_count'] ?></span>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 수정/삭제 버튼 -->
    <div class="card-footer bg-white d-flex gap-2 justify-content-end">
        <?php
        $userId = session()->get('user_id');
        $role   = session()->get('user_role') ?? 'guest';
        $canEdit = $role === 'admin' || ($userId && $post['user_id'] == $userId);
        ?>
        <?php if ($canEdit): ?>
            <a href="/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">수정</a>
            <form method="post" action="/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?>/delete"
                  onsubmit="return confirm('이 게시글을 삭제하시겠습니까? 되돌릴 수 없습니다.')">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger">삭제</button>
            </form>
        <?php elseif (!$userId && $post['author_password']): ?>
            <!-- 비회원 수정/삭제: 비밀번호 입력 모달 -->
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#guestModal" data-action="edit">수정</button>
            <button class="btn btn-sm btn-outline-danger"    data-bs-toggle="modal" data-bs-target="#guestModal" data-action="delete">삭제</button>
        <?php endif; ?>
    </div>
</div>

<!-- 댓글 -->
<div id="comments" class="card mb-4">
    <div class="card-header bg-white">
        <h2 class="h6 mb-0">댓글 <?= count($comments) ?>개</h2>
    </div>
    <div class="list-group list-group-flush">
        <?php foreach ($comments as $c): ?>
        <div class="list-group-item comment-box ps-3">
            <div class="d-flex justify-content-between">
                <strong class="small"><?= esc($c['user_nickname'] ?? mask_name($c['author_name'])) ?></strong>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small"><?= substr($c['created_at'], 0, 16) ?></span>
                    <?php if ($role === 'admin' || ($userId && $c['user_id'] == $userId)): ?>
                    <form method="post" action="/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?>/comment/<?= $c['id'] ?>/delete"
                          onsubmit="return confirm('이 댓글을 삭제하시겠습니까?')">
                        <?= csrf_field() ?>
                        <button class="btn btn-link btn-sm text-danger p-0">삭제<span class="visually-hidden"> — <?= esc($c['user_nickname'] ?? mask_name($c['author_name'])) ?>님의 댓글</span></button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <p class="mb-0 mt-1 small"><?= nl2br(esc($c['content'])) ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 댓글 작성 -->
    <div class="card-footer bg-white">
        <form method="post" action="/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?>/comment">
            <?= csrf_field() ?>
            <?php if (! session()->get('user_id')): ?>
            <div class="row g-2 mb-2">
                <div class="col-sm-3">
                    <label class="visually-hidden" for="comment-author-name">이름</label>
                    <input type="text" name="author_name" id="comment-author-name" class="form-control form-control-sm"
                           placeholder="이름" required autocomplete="name">
                </div>
                <div class="col-sm-3">
                    <label class="visually-hidden" for="comment-author-password">비밀번호</label>
                    <input type="password" name="author_password" id="comment-author-password" class="form-control form-control-sm"
                           placeholder="비밀번호" required autocomplete="new-password">
                </div>
            </div>
            <?php endif; ?>
            <div class="d-flex gap-2">
                <label class="visually-hidden" for="comment-content">댓글 내용</label>
                <textarea name="content" id="comment-content" class="form-control form-control-sm" rows="2" placeholder="댓글을 입력하세요" required></textarea>
                <button class="btn btn-primary btn-sm px-3">등록</button>
            </div>
        </form>
    </div>
</div>

<!-- 비회원 수정/삭제 모달 -->
<div class="modal fade" id="guestModal" tabindex="-1" aria-labelledby="guestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post" id="guestForm">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h2 class="modal-title h6 mb-0" id="guestModalLabel">비밀번호 확인</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="guest-password">작성 시 입력한 비밀번호</label>
                    <input type="password" name="author_password" id="guest-password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary btn-sm">확인</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
const guestModal = document.getElementById('guestModal');
guestModal.addEventListener('show.bs.modal', function(e) {
    const action = e.relatedTarget.dataset.action;
    const base = '/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?>';
    document.getElementById('guestForm').action = action === 'edit' ? base + '/verify' : base + '/delete';
});
</script>
<?= $this->endSection() ?>
