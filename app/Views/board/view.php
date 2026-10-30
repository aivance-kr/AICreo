<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="post-progress" role="progressbar" aria-label="읽기 진행률" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span class="post-progress__bar"></span></div>

<div class="container py-4">
<?php
$userId  = session()->get('user_id');
$role    = session()->get('user_role') ?? 'guest';
$canEdit = $role === 'admin' || ($userId && $post['user_id'] == $userId);
$postBase = '/board/' . esc($board['slug']) . '/' . $post['id'];
ob_start();
?>
<?php if ($canEdit): ?>
    <a href="<?= $postBase ?>/edit<?= esc($listQs, 'attr') ?>" class="btn btn-sm btn-outline-secondary">수정</a>
    <form method="post" action="<?= $postBase ?>/delete<?= esc($listQs, 'attr') ?>"
          onsubmit="return confirm('이 게시글을 삭제하시겠습니까? 되돌릴 수 없습니다.')">
        <?= csrf_field() ?>
        <button class="btn btn-sm btn-outline-danger">삭제</button>
    </form>
<?php elseif (! $userId && $post['author_password']): ?>
    <!-- 비회원 수정/삭제: 비밀번호 입력 모달 -->
    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#guestModal" data-action="edit">수정</button>
    <button class="btn btn-sm btn-outline-danger"    data-bs-toggle="modal" data-bs-target="#guestModal" data-action="delete">삭제</button>
<?php endif; ?>
<?php $postActions = ob_get_clean(); ?>

<div class="mb-2">
    <a href="/board/<?= esc($board['slug']) ?><?= esc($listQs, 'attr') ?>" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= esc($board['name']) ?> 목록
    </a>
</div>

<div class="card mb-4">
    <div class="card-header bg-white">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <h1 class="board-view-title fw-bold mb-1">
                <?php if (! empty($post['category_name'])): ?><span class="badge text-bg-secondary"><?= esc($post['category_name']) ?></span> <?php endif; ?>
                <?= esc($post['title']) ?>
            </h1>
            <div class="d-flex gap-2 flex-shrink-0">
                <?= $postActions ?>
            </div>
        </div>
        <?= view('board/_share', ['url' => $canonicalUrl, 'shortUrl' => $shortUrl, 'title' => $post['title'], 'kakaoJsKey' => $kakaoJsKey]) ?>
        <?php $postTags = \App\Models\PostModel::splitTags($post['tags'] ?? null); ?>
        <?php if ($postTags !== []): ?>
        <div class="mb-1">
            <span class="visually-hidden">태그</span>
            <?php foreach ($postTags as $tag): ?><a href="/board/<?= esc($board['slug']) ?>?type=tag&amp;keyword=<?= urlencode($tag) ?>" class="badge text-bg-light border text-decoration-none">#<?= esc($tag) ?></a> <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="d-flex gap-3 text-muted small">
            <span><i class="bi bi-person" aria-hidden="true"></i><span class="visually-hidden">작성자 </span> <?= esc($post['user_nickname'] ?? mask_name($post['author_name'])) ?></span>
            <span><i class="bi bi-clock" aria-hidden="true"></i><span class="visually-hidden">작성일 </span> <?= $post['created_at'] ?></span>
            <span><i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">조회수 </span> <?= number_format($post['views']) ?></span>
        </div>
        <?php foreach ($seriesNavs as $seriesNav): ?>
        <?= view('board/_series_nav', ['seriesNav' => $seriesNav]) ?>
        <?php endforeach; ?>
    </div>
    <div class="card-body">
        <!-- 본문 상단 광고 -->
        <?= view('components/ad_slot', ['ads' => $postTopAds]) ?>

        <div class="post-content board-post-content">
            <?= post_content_html($post['content']) ?>
        </div>

        <?php if (! empty($post['signature'])): ?>
        <div class="post-signature post-content board-post-content mt-4 pt-3 border-top">
            <?= post_content_html($post['signature']) ?>
        </div>
        <?php endif; ?>

        <!-- 공감(좋아요) -->
        <form id="post-like" method="post" action="<?= $postBase ?>/like<?= esc($listQs, 'attr') ?>" class="text-center my-4">
            <?= csrf_field() ?>
            <button type="submit" class="btn <?= $liked ? 'btn-danger' : 'btn-outline-danger' ?>" aria-pressed="<?= $liked ? 'true' : 'false' ?>">
                <i class="bi <?= $liked ? 'bi-heart-fill' : 'bi-heart' ?>" aria-hidden="true"></i>
                공감 <span class="fw-bold"><?= number_format($likeCount) ?></span>
            </button>
        </form>

        <!-- 본문 하단 광고 -->
        <?= view('components/ad_slot', ['ads' => $postBotAds]) ?>

        <!-- 이미지 첨부 -->
        <?php $images = array_filter($files, fn($f) => $f['is_image']); ?>
        <?php if ($images): ?>
        <hr>
        <div class="row g-2 mt-2">
            <?php foreach ($images as $img): ?>
            <div class="col-auto">
                <a href="/<?= esc($img['file_path']) ?>" target="_blank" rel="noopener">
                    <img loading="lazy" src="<?= esc(thumb_url($img['file_path'])) ?>" alt="<?= esc($img['original_name']) ?>"
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
        <?= $postActions ?>
    </div>
</div>

<?php if ($prevPost || $nextPost): ?>
<!-- 이전글 / 다음글 -->
<div class="card mb-4">
    <div class="list-group list-group-flush">
        <?php if ($nextPost): ?>
        <a href="/board/<?= esc($board['slug']) ?>/<?= $nextPost['id'] ?><?= esc($listQs, 'attr') ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
            <span class="text-muted small flex-shrink-0" style="width:4.5rem;"><i class="bi bi-arrow-up" aria-hidden="true"></i> 다음글</span>
            <span class="text-truncate"><?= esc($nextPost['title']) ?></span>
        </a>
        <?php endif; ?>
        <?php if ($prevPost): ?>
        <a href="/board/<?= esc($board['slug']) ?>/<?= $prevPost['id'] ?><?= esc($listQs, 'attr') ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
            <span class="text-muted small flex-shrink-0" style="width:4.5rem;"><i class="bi bi-arrow-down" aria-hidden="true"></i> 이전글</span>
            <span class="text-truncate"><?= esc($prevPost['title']) ?></span>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (! empty($relatedPosts)): ?>
<!-- 관련 글 -->
<section class="mb-4" aria-labelledby="related-posts-title">
    <h2 id="related-posts-title" class="h6 mb-3">이어 읽을 글</h2>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
        <?php foreach ($relatedPosts as $related): ?>
        <div class="col">
            <a href="/board/<?= esc($related['board_slug'], 'attr') ?>/<?= (int) $related['id'] ?>" class="card h-100 text-decoration-none">
                <?php if ($related['thumb']): ?>
                <img src="<?= esc($related['thumb'], 'attr') ?>" alt="" class="card-img-top" loading="lazy" style="height:120px;object-fit:cover">
                <?php endif; ?>
                <div class="card-body p-2">
                    <span class="small"><?= esc($related['title']) ?></span>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

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
                    <form method="post" action="/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?>/comment/<?= $c['id'] ?>/delete<?= esc($listQs, 'attr') ?>"
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
        <form method="post" action="/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?>/comment<?= esc($listQs, 'attr') ?>">
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
            <div class="row g-2 mb-2">
                <div class="col-sm-3">
                    <label class="visually-hidden" for="comment-captcha-answer">스팸 방지 계산</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text" aria-hidden="true"><?= esc($guestCommentCaptcha['question']) ?></span>
                        <input type="text" inputmode="numeric" name="captcha_answer" id="comment-captcha-answer" class="form-control"
                               placeholder="답" required autocomplete="off" aria-label="스팸 방지 계산 답">
                    </div>
                </div>
                <div class="visually-hidden" aria-hidden="true">
                    <label for="comment-website">웹사이트</label>
                    <input type="text" name="website" id="comment-website" tabindex="-1" autocomplete="off">
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
<script src="<?= asset_url('js/post-toc.js') ?>" defer></script>
<script src="<?= asset_url('js/code-highlight.js') ?>" defer></script>
<script src="<?= asset_url('js/post-share.js') ?>" defer></script>
<script>
const guestModal = document.getElementById('guestModal');
guestModal.addEventListener('show.bs.modal', function(e) {
    const action = e.relatedTarget.dataset.action;
    const base = '/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?>';
    document.getElementById('guestForm').action = action === 'edit' ? base + '/verify' : base + '/delete';
});
</script>
<?= $this->endSection() ?>
