<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold mb-1">최신 글</h1>
                <p class="text-muted mb-0">새로 등록된 글을 확인하세요.</p>
            </div>
        </div>

        <?php if ($latestBlogPosts !== []): ?>
            <div class="list-group list-group-flush border-top">
                <?php foreach ($latestBlogPosts as $post): ?>
                    <article class="list-group-item py-4">
                        <div class="d-flex justify-content-between gap-3">
                            <div>
                                <span class="badge text-bg-light mb-2"><?= esc($post['board_name']) ?></span>
                                <h2 class="h5 mb-3"><a class="text-decoration-none" href="/board/<?= esc($post['board_slug']) ?>/<?= esc($post['id']) ?>"><?= esc($post['title']) ?></a></h2>
                                <div class="post-content blog-post-body home-post-content mb-3"><?= $post['content'] ?></div>
                                <div class="blog-post-meta text-muted small">
                                    <span><i class="bi bi-person" aria-hidden="true"></i><span class="visually-hidden">작성자 </span><?= esc($post['user_nickname'] ?? mask_name($post['author_name'] ?? '')) ?></span>
                                    <span><i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">조회수 </span><?= number_format((int) ($post['views'] ?? 0)) ?></span>
                                </div>
                            </div>
                            <time class="text-muted small text-nowrap" datetime="<?= esc($post['created_at']) ?>">
                                <?= esc(substr((string) $post['created_at'], 0, 10)) ?>
                            </time>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">표시할 공개 글이 아직 없습니다.</p>
        <?php endif; ?>
    </div>
</section>

<?= $this->endSection() ?>
