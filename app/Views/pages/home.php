<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<!-- 메인 상단 배너 -->
<?= view('components/banner_slot', ['banners' => $mainTopBanners]) ?>

<!-- 히어로 -->
<section class="py-5 bg-primary text-white">
    <div class="container py-4 text-center">
        <h1 class="display-5 fw-bold"><?= esc($settings['site_name'] ?? '') ?></h1>
        <p class="lead mb-4"><?= esc($settings['site_desc'] ?? '') ?></p>
        <a href="/contact" class="btn btn-light btn-lg me-2">문의하기</a>
        <a href="/service" class="btn btn-outline-light btn-lg text-white fw-bold">서비스 보기</a>
    </div>
</section>

<!-- 서비스 소개 (샘플 3열) -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-4 fw-bold">우리의 서비스</h2>
        <div class="row g-4">
            <?php
            $services = [
                ['icon' => 'bi-laptop',    'title' => '웹사이트 제작', 'desc' => '기획부터 디자인, 개발까지 한 번에 진행합니다.'],
                ['icon' => 'bi-phone',     'title' => '반응형 디자인', 'desc' => 'PC·태블릿·모바일 어디서 봐도 깨지지 않는 화면을 만듭니다.'],
                ['icon' => 'bi-headset',   'title' => '유지보수',      'desc' => '오픈 후에도 콘텐츠 수정과 오류 대응까지 함께합니다.'],
            ];
            foreach ($services as $s):
            ?>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm text-center p-4">
                    <i class="bi <?= esc($s['icon']) ?> fs-1 text-primary mb-3" aria-hidden="true"></i>
                    <h3 class="h5 fw-bold"><?= esc($s['title']) ?></h3>
                    <p class="text-muted small mb-0"><?= esc($s['desc']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 최신 공지 -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 fw-bold mb-0">최신 공지사항</h2>
            <?php if (!empty($latestPosts)): ?>
            <a href="/board/notice" class="text-decoration-none small">전체보기 <i class="bi bi-chevron-right" aria-hidden="true"></i></a>
            <?php endif; ?>
        </div>
        <?php if (!empty($latestPosts)): ?>
        <div class="list-group">
            <?php foreach ($latestPosts as $post): ?>
            <a href="/board/notice/<?= $post['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <span><?= esc($post['title']) ?></span>
                <span class="text-muted small"><?= substr($post['created_at'], 0, 10) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="list-group">
            <div class="list-group-item text-muted small">
                아직 등록된 공지사항이 없습니다.
                <?php if ($authUser['role'] === 'admin'): ?>
                    <a href="/board/notice/write" class="text-decoration-none">첫 공지 등록하기 <i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- CTA -->
<section class="py-5 bg-dark text-white text-center">
    <div class="container">
        <h2 class="h3 fw-bold mb-2">프로젝트를 시작할 준비가 되셨나요?</h2>
        <p class="text-white-50 mb-4">궁금한 점을 남겨주시면 담당자가 직접 확인 후 연락드립니다.</p>
        <?php if (!empty($settings['phone'])): ?>
            <a href="tel:<?= esc($settings['phone']) ?>" class="btn btn-outline-light me-2">
                <i class="bi bi-telephone me-1" aria-hidden="true"></i><span class="visually-hidden">전화 </span><?= esc($settings['phone']) ?>
            </a>
        <?php endif; ?>
        <a href="/contact" class="btn btn-primary">온라인 문의</a>
    </div>
</section>

<!-- 메인 하단 배너 -->
<?= view('components/banner_slot', ['banners' => $mainBotBanners]) ?>

<?= $this->endSection() ?>
