<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $seo = new \App\Libraries\SeoHelper($settings);
    echo $seo->render($page ?? null);
    echo $seo->gaScript();

    // JSON-LD (GEO): 전 페이지 공통 Organization·WebSite + 페이지별 그래프
    $ld     = new \App\Libraries\Seo\JsonLdBuilder();
    $graphs = [$ld->organization($settings), $ld->website($settings)];
    foreach (($jsonLd ?? []) as $node) {
        $graphs[] = $node;
    }
    echo $ld->render($graphs);
    ?>
    <?php if (!empty($settings['favicon'])): ?>
    <link rel="icon" href="/<?= esc($settings['favicon']) ?>">
    <?php else: ?>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <?php endif; ?>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
          integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="stylesheet" href="/css/tokens.css">
    <link rel="stylesheet" href="/themes/default/css/style.css">
    <?= $this->renderSection('head') ?>
</head>
<body>

<a class="skip-link" href="#main-content">본문 바로가기</a>

<?= $this->include('components/navbar') ?>

<div class="d-flex align-items-start">
    <?php if (!empty($subLeftBanners)): ?>
    <aside class="sp-banner-slot d-none d-md-block flex-shrink-0 p-2" aria-label="배너">
        <?php foreach ($subLeftBanners as $b): ?>
        <?php $alt = trim((string) ($b['alt_text'] ?? '')); ?>
        <?php if ($b['link_url']): ?>
        <a href="<?= esc($b['link_url']) ?>" target="<?= esc($b['link_target']) ?>"<?= $b['link_target'] === '_blank' ? ' rel="noopener"' : '' ?>>
            <img src="/<?= esc($b['image_path']) ?>" alt="<?= esc($alt !== '' ? $alt : '배너') ?>" class="sp-banner-img">
            <?php if ($b['link_target'] === '_blank'): ?><span class="visually-hidden">(새 창 열림)</span><?php endif; ?>
        </a>
        <?php else: ?>
        <img src="/<?= esc($b['image_path']) ?>" alt="<?= esc($alt) ?>" class="sp-banner-img">
        <?php endif; ?>
        <?php endforeach; ?>
    </aside>
    <?php endif; ?>

    <main id="main-content" tabindex="-1" class="flex-grow-1 min-width-0">
        <?php foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $cls): ?>
            <?php if (session()->has($key)): ?>
            <div class="container mt-3">
                <div class="alert alert-<?= $cls ?> alert-dismissible fade show" role="alert">
                    <?= esc(session($key)) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="알림 닫기"></button>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?= $this->renderSection('content') ?>
    </main>
</div>

<?= $this->include('components/footer') ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer
        integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<script src="/themes/default/js/main.js" defer></script>
<?= $this->renderSection('scripts') ?>
<?= $this->include('components/popups') ?>
<script src="/themes/default/js/popup.js" defer></script>
</body>
</html>
