<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="post-progress" role="progressbar" aria-label="<?= esc(lang('Board.readProgress'), 'attr') ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span class="post-progress__bar"></span></div>

<div class="container py-4">
<?php
$userId  = session()->get('user_id');
$role    = session()->get('user_role') ?? 'guest';
$canEdit = $role === 'admin' || ($userId && $post['user_id'] == $userId);
$postBase = '/board/' . esc($board['slug']) . '/' . $post['id'];
ob_start();
?>
<?php if ($canEdit): ?>
    <a href="<?= $postBase ?>/edit<?= esc($listQs, 'attr') ?>" class="btn btn-sm btn-outline-secondary"><?= esc(lang('Board.edit')) ?></a>
    <form method="post" action="<?= $postBase ?>/delete<?= esc($listQs, 'attr') ?>"
          onsubmit="return confirm('<?= esc(lang('Board.confirmDeletePost'), 'js') ?>')">
        <?= csrf_field() ?>
        <button class="btn btn-sm btn-outline-danger"><?= esc(lang('Board.delete')) ?></button>
    </form>
<?php elseif (! $userId && $post['author_password']): ?>
    <!-- 비회원 수정/삭제: 비밀번호 입력 모달 -->
    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#guestModal" data-action="edit"><?= esc(lang('Board.edit')) ?></button>
    <button class="btn btn-sm btn-outline-danger"    data-bs-toggle="modal" data-bs-target="#guestModal" data-action="delete"><?= esc(lang('Board.delete')) ?></button>
<?php endif; ?>
<?php $postActions = ob_get_clean(); ?>
<?php $preview = $preview ?? false; // 미리보기 공유 링크: 읽기 전용 ?>
<?php if ($preview): $postActions = ''; endif; ?>

<div class="mb-2">
    <a href="/board/<?= esc($board['slug']) ?><?= esc($listQs, 'attr') ?>" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= esc(lang('Board.backToList', [$board['name']])) ?>
    </a>
</div>

<article class="post">
    <!-- 1) 제목 블록: 제목 + 메타 한 줄 + 공유 아이콘 -->
    <header class="post-head">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <h1 class="post-title fw-bold"><?= esc($post['title']) ?></h1>
            <div class="d-flex gap-2 flex-shrink-0">
                <?= $postActions ?>
            </div>
        </div>
        <div class="post-meta">
            <?php if (! empty($post['category_name'])): ?>
            <span class="post-meta__item"><span class="visually-hidden"><?= esc(lang('Board.category')) ?> </span><?= esc($post['category_name']) ?></span>
            <?php endif; ?>
            <span class="post-meta__item"><span class="visually-hidden"><?= esc(lang('Board.author')) ?> </span><?= esc($post['user_nickname'] ?? mask_name($post['author_name'])) ?></span>
            <span class="post-meta__item"><span class="visually-hidden"><?= esc(lang('Board.createdAt')) ?> </span><time datetime="<?= esc(substr((string) $post['created_at'], 0, 10), 'attr') ?>"><?= esc(str_replace('-', '.', substr((string) $post['created_at'], 0, 10))) ?></time></span>
            <span class="post-meta__item"><span class="visually-hidden"><?= esc(lang('Board.viewCount')) ?> </span><?= esc(lang('Board.views')) ?> <?= number_format($post['views']) ?></span>
            <?php if (! $preview): ?>
            <?= view('board/_share', ['url' => $canonicalUrl, 'shortUrl' => $shortUrl, 'title' => $post['title'], 'kakaoJsKey' => $kakaoJsKey]) ?>
            <?php endif; ?>
        </div>
    </header>

    <!-- 2) 보조 영역: 시리즈 + 목차(post-toc.js 가 본문 앞에 끼워 넣어 이 영역 아래에 붙는다) -->
    <?php if ($seriesNavs !== []): ?>
    <div class="post-aux">
        <?php foreach ($seriesNavs as $seriesNav): ?>
        <?= view('board/_series_nav', ['seriesNav' => $seriesNav]) ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 3) 본문 -->
    <div class="post-body">
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

        <?php $images = array_filter($files, fn($f) => $f['is_image']); ?>
        <?php $attachments = array_filter($files, fn($f) => !$f['is_image']); ?>
        <?php if ($images || $attachments): ?>
        <div class="post-files" aria-label="<?= esc(lang('Board.attachments'), 'attr') ?>">
            <!-- 이미지 첨부 -->
            <?php if ($images): ?>
            <div class="row g-2">
                <?php foreach ($images as $img): ?>
                <div class="col-auto">
                    <a href="/<?= esc($img['file_path']) ?>" target="_blank" rel="noopener">
                        <img loading="lazy" src="<?= esc(thumb_url($img['file_path'])) ?>" alt="<?= esc($img['original_name']) ?>"
                             class="img-thumbnail" style="max-height:200px;">
                        <span class="visually-hidden"><?= esc(lang('Board.viewOriginal')) ?></span>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- 파일 첨부 -->
            <?php if ($attachments): ?>
            <div class="file-list<?= $images ? ' mt-3' : '' ?>">
                <div class="text-muted small mb-1"><i class="bi bi-paperclip" aria-hidden="true"></i> <?= esc(lang('Board.attachedFiles')) ?></div>
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
        <?php endif; ?>


        <?php $postTags = \App\Models\PostModel::splitTags($post['tags'] ?? null); ?>
        <?php if ($postTags !== []): ?>
        <div class="post-tags">
            <span class="visually-hidden"><?= esc(lang('Board.tag')) ?></span>
            <?php foreach ($postTags as $tag): ?><a href="/board/<?= esc($board['slug']) ?>?type=tag&amp;keyword=<?= urlencode($tag) ?>" class="badge text-bg-light border text-decoration-none">#<?= esc($tag) ?></a> <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 공감(좋아요) -->
    <?php if (! $preview): ?>
    <form id="post-like" method="post" action="<?= $postBase ?>/like<?= esc($listQs, 'attr') ?>" class="text-center my-4">
        <?= csrf_field() ?>
        <button type="submit" class="btn <?= $liked ? 'btn-danger' : 'btn-outline-danger' ?>" aria-pressed="<?= $liked ? 'true' : 'false' ?>">
            <i class="bi <?= $liked ? 'bi-heart-fill' : 'bi-heart' ?>" aria-hidden="true"></i>
            <?= esc(lang('Board.likes')) ?> <span class="fw-bold"><?= number_format($likeCount) ?></span>
        </button>
    </form>
    <?= widget_slot('post_actions', ['post_id' => (int) $post['id']]) ?>
    <?php endif; ?>

    <!-- 본문 하단 광고 -->
    <?= view('components/ad_slot', ['ads' => $postBotAds]) ?>

    <!-- 수정/삭제 버튼 -->
    <?php if (trim($postActions) !== ''): ?>
    <div class="post-foot d-flex gap-2 justify-content-end">
        <?= $postActions ?>
    </div>
    <?php endif; ?>
</article>

<?php if ($prevPost || $nextPost): ?>
<!-- 이전글 / 다음글 -->
<nav class="post-section post-pager" aria-label="<?= esc(lang('Board.prevNextPost'), 'attr') ?>">
    <div class="list-group list-group-flush">
        <?php if ($nextPost): ?>
        <a href="/board/<?= esc($board['slug']) ?>/<?= $nextPost['id'] ?><?= esc($listQs, 'attr') ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
            <span class="text-muted small flex-shrink-0" style="width:4.5rem;"><i class="bi bi-arrow-up" aria-hidden="true"></i> <?= esc(lang('Board.nextPost')) ?></span>
            <span class="text-truncate"><?= esc($nextPost['title']) ?></span>
        </a>
        <?php endif; ?>
        <?php if ($prevPost): ?>
        <a href="/board/<?= esc($board['slug']) ?>/<?= $prevPost['id'] ?><?= esc($listQs, 'attr') ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
            <span class="text-muted small flex-shrink-0" style="width:4.5rem;"><i class="bi bi-arrow-down" aria-hidden="true"></i> <?= esc(lang('Board.prevPost')) ?></span>
            <span class="text-truncate"><?= esc($prevPost['title']) ?></span>
        </a>
        <?php endif; ?>
    </div>
</nav>
<?php endif; ?>

<?php if (! empty($relatedPosts)): ?>
<!-- 관련 글 -->
<section class="post-section" aria-labelledby="related-posts-title">
    <h2 id="related-posts-title" class="post-section__title"><?= esc(lang('Board.relatedPosts')) ?></h2>
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

<?php if (! $preview): ?>
<!-- 댓글 -->
<section id="comments" class="post-section" aria-labelledby="comments-title">
    <h2 id="comments-title" class="post-section__title"><?= esc(lang('Board.comments')) ?> <span class="text-muted fw-normal"><?= count($comments) ?></span></h2>
    <?php if ($comments === []): ?>
    <p class="text-muted small mb-3"><?= esc(lang('Board.noComments')) ?></p>
    <?php else: ?>
    <div class="list-group list-group-flush mb-3">
        <?php foreach ($comments as $c): ?>
        <div class="list-group-item comment-box px-0">
            <div class="d-flex justify-content-between">
                <strong class="small"><?= esc($c['user_nickname'] ?? mask_name($c['author_name'])) ?></strong>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small"><?= substr($c['created_at'], 0, 16) ?></span>
                    <?php if ($role === 'admin' || ($userId && $c['user_id'] == $userId)): ?>
                    <form method="post" action="/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?>/comment/<?= $c['id'] ?>/delete<?= esc($listQs, 'attr') ?>"
                          onsubmit="return confirm('<?= esc(lang('Board.confirmDeleteComment'), 'js') ?>')">
                        <?= csrf_field() ?>
                        <button class="btn btn-link btn-sm text-danger p-0"><?= esc(lang('Board.delete')) ?><span class="visually-hidden"> — <?= esc(lang('Board.commentBy', [$c['user_nickname'] ?? mask_name($c['author_name'])])) ?></span></button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <p class="mb-0 mt-1 small"><?= nl2br(esc($c['content'])) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 댓글 작성 -->
    <form method="post" action="/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?>/comment<?= esc($listQs, 'attr') ?>">
        <?= csrf_field() ?>
        <?php if (! session()->get('user_id')): ?>
        <div class="row g-2 mb-2">
            <div class="col-sm-4">
                <label class="visually-hidden" for="comment-author-name"><?= esc(lang('Board.name')) ?></label>
                <input type="text" name="author_name" id="comment-author-name" class="form-control"
                       placeholder="<?= esc(lang('Board.name'), 'attr') ?>" required autocomplete="name">
            </div>
            <div class="col-sm-4">
                <label class="visually-hidden" for="comment-author-password"><?= esc(lang('Board.password')) ?></label>
                <input type="password" name="author_password" id="comment-author-password" class="form-control"
                       placeholder="<?= esc(lang('Board.password'), 'attr') ?>" required autocomplete="new-password">
            </div>
            <div class="col-sm-4">
                <label class="visually-hidden" for="comment-captcha-answer"><?= esc(lang('Board.captchaLabel')) ?></label>
                <div class="input-group">
                    <span class="input-group-text" aria-hidden="true"><?= esc($guestCommentCaptcha['question']) ?></span>
                    <input type="text" inputmode="numeric" name="captcha_answer" id="comment-captcha-answer" class="form-control"
                           placeholder="<?= esc(lang('Board.captchaAnswerPlaceholder'), 'attr') ?>" required autocomplete="off" aria-label="<?= esc(lang('Board.captchaAnswerLabel'), 'attr') ?>">
                </div>
            </div>
            <div class="visually-hidden" aria-hidden="true">
                <label for="comment-website"><?= esc(lang('Board.website')) ?></label>
                <input type="text" name="website" id="comment-website" tabindex="-1" autocomplete="off">
            </div>
        </div>
        <?php endif; ?>
        <label class="visually-hidden" for="comment-content"><?= esc(lang('Board.commentContent')) ?></label>
        <textarea name="content" id="comment-content" class="form-control mb-2" rows="3" placeholder="<?= esc(lang('Board.commentPlaceholder'), 'attr') ?>" required></textarea>
        <div class="text-end"><button class="btn btn-primary px-4"><?= esc(lang('Board.commentSubmit')) ?></button></div>
    </form>
</section>

<!-- 비회원 수정/삭제 모달 -->
<div class="modal fade" id="guestModal" tabindex="-1" aria-labelledby="guestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post" id="guestForm">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h2 class="modal-title h6 mb-0" id="guestModalLabel"><?= esc(lang('Board.confirmPassword')) ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('Board.close'), 'attr') ?>"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="guest-password"><?= esc(lang('Board.postPassword')) ?></label>
                    <input type="password" name="author_password" id="guest-password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary btn-sm"><?= esc(lang('Board.confirm')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= asset_url('js/post-toc.js') ?>" defer></script>
<script src="<?= asset_url('js/code-highlight.js') ?>" defer></script>
<script src="<?= asset_url('js/post-share.js') ?>" defer></script>
<?php if (! $preview): ?>
<script>
const guestModal = document.getElementById('guestModal');
guestModal.addEventListener('show.bs.modal', function(e) {
    const action = e.relatedTarget.dataset.action;
    const base = '/board/<?= esc($board['slug']) ?>/<?= $post['id'] ?>';
    document.getElementById('guestForm').action = action === 'edit' ? base + '/verify' : base + '/delete';
});
</script>
<?php endif; ?>
<?= $this->endSection() ?>
