<?php
$uri = uri_string();

// 사이드바 활성 항목 판정 — 설정 3종은 접두어가 겹치므로 먼저 갈라낸다.
$isThemeSettings   = str_starts_with($uri, 'admin/settings/theme');
$isOauthSettings   = str_starts_with($uri, 'admin/settings/oauth');
$isBackup           = str_starts_with($uri, 'admin/backup');
$isGeneralSettings = str_starts_with($uri, 'admin/settings') && ! $isThemeSettings && ! $isOauthSettings;
$isDashboard       = $uri === 'admin' || $uri === 'admin/dashboard';

// 사이드바 메뉴 정의 — [그룹 제목, 아이콘, 항목[]]. 항목: [주소, 아이콘, 이름, 활성 여부].
// 모든 그룹은 항상 펼쳐 보여준다(찾기 쉽도록 접지 않음).
$active = static fn (string $prefix): bool => str_starts_with($uri, $prefix);
$navGroups = [
    ['key' => 'content', 'title' => '콘텐츠', 'icon' => 'file-earmark-text', 'items' => [
        ['/admin/pages', 'file-text', '페이지 관리', $active('admin/pages')],
        ['/admin/boards', 'card-list', '게시판 관리', $active('admin/boards')],
        ['/admin/posts', 'file-earmark-text', '전체 게시물', $active('admin/posts')],
        ['/admin/series', 'collection', '시리즈 관리', $active('admin/series')],
        ['/admin/comments', 'chat-left-text', '전체 댓글', $active('admin/comments')],
        ['/admin/trash', 'trash', '휴지통', $active('admin/trash')],
    ]],
    ['key' => 'media', 'title' => '미디어·노출', 'icon' => 'images', 'items' => [
        ['/admin/media', 'images', '미디어', $active('admin/media')],
        ['/admin/banners', 'image', '배너 관리', $active('admin/banners')],
        ['/admin/popups', 'window', '팝업 관리', $active('admin/popups')],
        ['/admin/ads', 'megaphone', '광고 관리', $active('admin/ads')],
    ]],
    ['key' => 'contact', 'title' => '문의·구독', 'icon' => 'envelope', 'badge' => (int) $unreadInquiries, 'items' => [
        ['/admin/inquiries', 'envelope', '문의 수신함', $active('admin/inquiries'), (int) $unreadInquiries],
        ['/admin/inquiry-fields', 'ui-checks', '문의 폼 항목', $active('admin/inquiry-fields')],
        ['/admin/subscribers', 'envelope-paper', '뉴스레터 구독자', $active('admin/subscribers')],
    ]],
    ['key' => 'site', 'title' => '사이트 구성', 'icon' => 'diagram-3', 'items' => [
        ['/admin/menus', 'list-ul', '메뉴 관리', $active('admin/menus')],
        ['/admin/redirects', 'signpost-split', '리다이렉트', $active('admin/redirects')],
        ['/admin/plugins', 'plug', '플러그인', $active('admin/plugins')],
    ]],
    ['key' => 'ops', 'title' => '통계·보안', 'icon' => 'bar-chart', 'items' => [
        ['/admin/stats', 'bar-chart', '접속 통계', $active('admin/stats')],
        ['/admin/blocked-ips', 'slash-circle', '차단 IP', $active('admin/blocked-ips')],
        ['/admin/logs', 'journal-text', '활동 로그', $active('admin/logs')],
        ['/admin/users', 'person-lines-fill', '회원 관리', $active('admin/users')],
    ]],
    ['key' => 'settings', 'title' => '설정', 'icon' => 'gear', 'items' => [
        ['/admin/settings/general', 'gear', '사이트 설정', $isGeneralSettings],
        ['/admin/settings/theme', 'palette', '테마 관리', $isThemeSettings],
        ['/admin/settings/oauth', 'people', '소셜 로그인', $isOauthSettings],
        ['/admin/security', 'shield-lock', '2단계 인증', $active('admin/security')],
        ['/admin/backup', 'database-down', '백업 관리', $isBackup],
    ]],
];

// 항목 하나라도 활성이면 그 그룹이 활성이다(상단바 그룹 버튼 강조용).
$groupActive = static function (array $group): bool {
    foreach ($group['items'] as $item) {
        if ($item[3]) {
            return true;
        }
    }

    return false;
};
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($pageTitle ?? '관리자') ?> | <?= esc($settings['site_name'] ?? 'Admin') ?></title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
          integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= asset_url('css/tokens.css') ?>">
    <style>
        body  { background: var(--surface-body); font-family: var(--font-sans); color: var(--text-body); }

        /* Bootstrap 오프캔버스가 992px 이상에서 배경을 transparent !important 로 강제 리셋한다.
           #sidebar 는 ID 선택자라 특정성은 더 높지만 !important 없이는 그 규칙을 이길 수 없다 —
           그래서 여기도 !important 로 맞춰 특정성 비교(ID > class)로 승부를 낸다. */
        #sidebar {
            width: var(--sidebar-w);
            background: var(--dark) !important;
            height: 100vh;
            max-height: 100dvh;
            overflow-y: auto;
            overscroll-behavior: contain;
        }
        /* 어두운 표면 위에서는 초점 링을 밝은 쪽으로 뒤집는다 */
        #sidebar { --focus-ring-color: var(--on-dark-strong); }
        #sidebar .brand { padding: 1rem 1.2rem; color: var(--on-dark-strong); font-weight: 700; font-size: 1.1rem; border-bottom: 1px solid var(--on-dark-raised); }
        #sidebar .nav-link { color: var(--on-dark-body); padding: .6rem 1.2rem; font-size: .875rem; }
        #sidebar .nav-link:hover,
        #sidebar .nav-link.active { color: var(--on-dark-strong); background: var(--on-dark-raised); }
        #sidebar .nav-section { color: var(--on-dark-muted); font-size: .7rem; padding: .8rem 1.2rem .2rem; letter-spacing: .06em; text-transform: uppercase; }
        #sidebar .nav-group { border-top: 1px solid var(--on-dark-raised); margin-top: .35rem; padding-bottom: .15rem; }
        #sidebar .nav-group .nav-section { padding-top: .6rem; }
        #sidebar .nav-link.sub { padding: .38rem 1.2rem .38rem 1.9rem; }
        /* Bootstrap .text-danger(#dc3545)는 사이드바 배경 위에서 3.2:1 로 AA 미달이었다 —
           어두운 표면 전용 danger 톤(5.2:1)으로 이 컨테이너 안에서만 되돌린다.
           .text-danger 자체가 !important 라 특정성이 아니라 !important 로만 이길 수 있다. */
        #sidebar .nav-link.text-danger { color: var(--on-dark-danger) !important; }
        #sidebar .nav-link.text-danger:hover { color: var(--on-dark-strong) !important; }

        #topbar { background: var(--surface-raised); border-bottom: 1px solid var(--border-subtle); padding: .6rem 1rem; position: sticky; top: 0; z-index: 100; }

        /* 데스크톱(992px↑): 사이드바 대신 상단바에 그룹 이름만 노출하고,
           그룹을 누르면 전체 메뉴 패널이 펼쳐진다. 그 아래 폭은 오프캔버스(#sidebar)가 맡는다. */
        /* 상단 그룹 버튼과 패널 칼럼은 같은 칼럼 폭(--mega-col)을 써서 같은 x 에 정렬된다. 첫 칼럼은 대시보드 / 바로가기 */
        #topnav, #megamenu .mega-groups { --mega-col: clamp(112px, 12vw, 168px); display: grid; grid-template-columns: repeat(7, var(--mega-col)); gap: 0; }
        #topnav .nav-btn { text-align: left; border: 0; background: transparent; color: var(--text-body); font-size: .875rem; padding: .35rem .7rem; border-radius: .375rem; white-space: nowrap; text-decoration: none; }
        #topnav .nav-btn:hover,
        #topnav .nav-btn[aria-expanded="true"],
        #topnav .nav-btn.active { background: var(--surface-body); color: var(--primary); }
        #topnav .nav-btn.active { font-weight: 700; }
        #megamenu { position: absolute; left: 0; right: 0; top: 100%; background: var(--dark); box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .25); max-height: calc(100dvh - 3.5rem); overflow-y: auto; --focus-ring-color: var(--on-dark-strong); }
        #megamenu .mega-groups { padding: 1rem 1.5rem; }
        #megamenu .nav-link { color: var(--on-dark-body); padding: .38rem .7rem; font-size: .875rem; border-radius: .25rem; }
        #megamenu .nav-link:hover,
        #megamenu .nav-link.active { color: var(--on-dark-strong); background: var(--on-dark-raised); }
        #megamenu .nav-section { color: var(--on-dark-muted); font-size: .7rem; padding: .4rem .7rem; letter-spacing: .06em; text-transform: uppercase; }
        #megamenu .nav-group.is-target .nav-section { color: var(--on-dark-strong); }
        #megamenu .nav-link.text-danger { color: var(--on-dark-danger) !important; }
        #sitebar { padding: .45rem 1.5rem; background: var(--dark); color: var(--on-dark-strong); font-size: .8125rem; font-weight: 700; }
        #pagebar { padding: .9rem 1.5rem; background: var(--surface-raised); border-bottom: 1px solid var(--border-subtle); }
        #content { padding: 1.25rem 1rem; }
        @media (min-width: 992px) {
            #topbar  { padding: .6rem 1.5rem; }
            #content { padding: 1.5rem; }
        }

        /* .btn-sm 터치 여유·.table-stack 반응형 표는 /css/tokens.css 로 옮겨
           프론트·관리자가 함께 참조한다(2026-08-19, 감사 후 통합). */

        .badge-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--danger); display: inline-block; }

        /* 도움말(매뉴얼) 렌더링 — CommonMark 출력 최소 타이포. 게시글 본문(post-content)과
           같은 줄높이 리듬을 따른다. 원본 마크다운의 h1~h4 는 컨트롤러에서 한 단계씩
           낮춰 h2~h5 로 렌더링된다(페이지의 실제 h1 과 중복되지 않도록) — 아래 선택자는
           그 낮춰진 레벨을 기준으로 한다. */
        .manual-content { line-height: 1.7; }
        .manual-content h2 { font-size: 1.25rem; margin: 0 0 1rem; }
        .manual-content h3 { font-size: 1.1rem; margin: 2rem 0 .75rem; padding-top: .5rem; border-top: 1px solid var(--border-hairline); }
        .manual-content h3:first-child { margin-top: 0; padding-top: 0; border-top: 0; }
        .manual-content h4 { font-size: 1rem; margin: 1.5rem 0 .5rem; }
        .manual-content h5 { font-size: .9rem; margin: 1.25rem 0 .5rem; }
        .manual-content p, .manual-content ul, .manual-content ol { margin-bottom: .75rem; }
        .manual-content table { width: 100%; margin-bottom: 1rem; border-collapse: collapse; font-size: .875rem; }
        .manual-content th, .manual-content td { padding: .5rem .75rem; border: 1px solid var(--border-subtle); text-align: left; }
        .manual-content th { background: var(--surface-body); }
        .manual-content code { background: var(--surface-body); padding: .1rem .35rem; border-radius: .25rem; font-size: .875em; }
        .manual-content pre { background: var(--surface-body); padding: 1rem; border-radius: .5rem; overflow-x: auto; }
        .manual-content pre code { background: none; padding: 0; }
        .manual-content blockquote { padding-left: 1rem; color: var(--text-muted); font-style: italic; margin: 0 0 .75rem; }
        .manual-content a { color: var(--primary); }

        /* 초점이 sticky 상단바 아래로 숨지 않게 한다 (WCAG 2.2 2.4.11, AA).
           Tab 으로 내려갈 때 브라우저가 요소를 화면 맨 위에 붙이는데,
           상단바가 그 자리를 덮고 있어 초점이 보이지 않게 된다. */
        #content :where(a, button, input, select, textarea, summary, [tabindex]) {
            scroll-margin-top: 4rem;
        }

        /* 화면에서 감춘 파일 입력은 초점을 받아도 보이지 않는다.
           대신 짝이 되는 버튼 모양 레이블에 초점 링을 그린다. */
        input[type="file"].visually-hidden:focus-visible + label.btn {
            outline: var(--focus-ring-width) solid var(--focus-ring-color);
            outline-offset: 2px;
        }
    </style>
</head>
<body>

<a class="skip-link" href="#main-content">본문 바로가기</a>

<div class="offcanvas offcanvas-start" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
    <div class="offcanvas-header">
        <span class="offcanvas-title h6 mb-0 text-white" id="sidebarLabel">관리자 메뉴</span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="메뉴 닫기"></button>
    </div>

    <div class="offcanvas-body p-0">
        <div class="brand"><i class="bi bi-grid-3x3-gap-fill me-2" aria-hidden="true"></i><?= esc($settings['site_name'] ?? 'Admin') ?></div>
        <nav class="mt-2" aria-label="관리자 메뉴">
            <a href="/admin/dashboard" class="nav-link <?= $isDashboard ? 'active' : '' ?>" <?= $isDashboard ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>대시보드
            </a>

            <div class="nav-groups">
            <?php foreach ($navGroups as $group): ?>
            <section class="nav-group" aria-labelledby="nav-<?= esc($group['key']) ?>">
                <div class="nav-section" id="nav-<?= esc($group['key']) ?>">
                    <i class="bi bi-<?= esc($group['icon']) ?> me-1" aria-hidden="true"></i><?= esc($group['title']) ?>
                    <?php if (($group['badge'] ?? 0) > 0): ?><span class="badge-dot ms-1" aria-hidden="true"></span><?php endif; ?>
                </div>
                <?php foreach ($group['items'] as $item): ?>
                <a href="<?= esc($item[0]) ?>" class="nav-link sub <?= $item[3] ? 'active' : '' ?>" <?= $item[3] ? 'aria-current="page"' : '' ?>>
                    <i class="bi bi-<?= esc($item[1]) ?> me-2" aria-hidden="true"></i><?= esc($item[2]) ?>
                    <?php if (($item[4] ?? 0) > 0): ?>
                    <span class="badge bg-danger ms-1"><?= esc((string) $item[4]) ?><span class="visually-hidden">건의 읽지 않은 문의</span></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </section>
            <?php endforeach; ?>
            </div>

            <div class="nav-section mt-3">사이트</div>
            <a href="/admin/help" class="nav-link <?= str_starts_with($uri, 'admin/help') ? 'active' : '' ?>" <?= str_starts_with($uri, 'admin/help') ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-question-circle me-2" aria-hidden="true"></i>도움말
            </a>
            <a href="/" target="_blank" rel="noopener" class="nav-link">
                <i class="bi bi-box-arrow-up-right me-2" aria-hidden="true"></i>사이트 보기<span class="visually-hidden"> (새 창 열림)</span>
            </a>
            <a href="/auth/logout" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>로그아웃</a>
        </nav>
    </div>
</div>

<div id="sitebar" class="d-none d-lg-block">
    <i class="bi bi-grid-3x3-gap-fill me-2" aria-hidden="true"></i><?= esc($settings['site_name'] ?? 'Admin') ?> <span class="fw-normal" style="color: var(--on-dark-muted)">관리자</span>
</div>

<header id="topbar" class="d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center">
        <button class="btn btn-sm btn-outline-secondary d-lg-none me-2" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="관리자 메뉴 열기">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <h1 class="h6 mb-0 text-dark d-lg-none"><?= esc($pageTitle ?? '관리자') ?></h1>
        <nav id="topnav" class="d-none d-lg-grid" aria-label="관리자 메뉴 그룹">
            <a href="/admin/dashboard" class="nav-btn <?= $isDashboard ? 'active' : '' ?>" data-mega-group="home"
               aria-expanded="false" aria-controls="megamenu" <?= $isDashboard ? 'aria-current="page"' : '' ?>>대시보드</a>
            <?php foreach ($navGroups as $group): ?>
            <a href="<?= esc($group['items'][0][0]) ?>" class="nav-btn <?= $groupActive($group) ? 'active' : '' ?>" data-mega-group="<?= esc($group['key']) ?>"
               aria-expanded="false" aria-controls="megamenu">
                <?= esc($group['title']) ?><?php if (($group['badge'] ?? 0) > 0): ?><span class="badge-dot ms-1" aria-hidden="true"></span><span class="visually-hidden"> (읽지 않은 문의 있음)</span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </div>
    <span class="text-muted small"><i class="bi bi-person-circle me-1" aria-hidden="true"></i><?= esc($authUser['nickname']) ?></span>

    <div id="megamenu" class="d-none" role="region" aria-label="전체 메뉴">
        <div class="mega-groups">
            <section class="nav-group border-0 mt-0" data-mega-section="home" aria-labelledby="mega-site">
                <div class="nav-section" id="mega-site">대시보드 · 바로가기</div>
                <a href="/admin/dashboard" class="nav-link <?= $isDashboard ? 'active' : '' ?>" <?= $isDashboard ? 'aria-current="page"' : '' ?>><i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>대시보드</a>
                <a href="/admin/help" class="nav-link"><i class="bi bi-question-circle me-2" aria-hidden="true"></i>도움말</a>
                <a href="/" target="_blank" rel="noopener" class="nav-link"><i class="bi bi-box-arrow-up-right me-2" aria-hidden="true"></i>사이트 보기<span class="visually-hidden"> (새 창 열림)</span></a>
                <a href="/auth/logout" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>로그아웃</a>
            </section>
            <?php foreach ($navGroups as $group): ?>
            <section class="nav-group border-0 mt-0" data-mega-section="<?= esc($group['key']) ?>" aria-labelledby="mega-<?= esc($group['key']) ?>">
                <div class="nav-section" id="mega-<?= esc($group['key']) ?>"><?= esc($group['title']) ?></div>
                <?php foreach ($group['items'] as $item): ?>
                <a href="<?= esc($item[0]) ?>" class="nav-link <?= $item[3] ? 'active' : '' ?>" <?= $item[3] ? 'aria-current="page"' : '' ?>>
                    <i class="bi bi-<?= esc($item[1]) ?> me-2" aria-hidden="true"></i><?= esc($item[2]) ?>
                    <?php if (($item[4] ?? 0) > 0): ?>
                    <span class="badge bg-danger ms-1"><?= esc((string) $item[4]) ?><span class="visually-hidden">건의 읽지 않은 문의</span></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </section>
            <?php endforeach; ?>
        </div>
    </div>
</header>

<?php
$activeGroupTitle = '';
foreach ($navGroups as $navGroup) {
    if ($groupActive($navGroup)) {
        $activeGroupTitle = $navGroup['title'];
        break;
    }
}
?>
<div id="pagebar" class="d-none d-lg-block">
    <?php if ($activeGroupTitle !== ''): ?><div class="small text-muted"><?= esc($activeGroupTitle) ?></div><?php endif; ?>
    <h1 class="h5 mb-0 text-dark"><?= esc($pageTitle ?? '관리자') ?></h1>
</div>

<main id="main-content" tabindex="-1">
<div id="content">
    <?php foreach (['success', 'error'] as $type): ?>
        <?php if (session()->has($type)): ?>
        <div class="alert alert-<?= $type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
            <?= esc(session($type)) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="알림 닫기"></button>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if (session()->has('errors')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php foreach (session('errors') as $e): ?><div><?= esc($e) ?></div><?php endforeach; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="알림 닫기"></button>
    </div>
    <?php endif; ?>

    <?= $this->renderSection('content') ?>
</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<script>
// 상단 그룹 링크: 마우스를 올리거나 초점을 주면 전체 메뉴 패널이 열리고 해당 그룹 제목이 강조된다.
// 클릭하면 그룹의 첫 메뉴로 이동한다. 상단바 밖으로 나가거나 Esc 를 누르면 닫힌다.
(function () {
    var panel = document.getElementById('megamenu');
    var header = document.getElementById('topbar');
    var buttons = document.querySelectorAll('[data-mega-group]');
    if (!panel || !header || !buttons.length) { return; }
    function setOpen(open, key) {
        panel.classList.toggle('d-none', !open);
        buttons.forEach(function (b) {
            b.setAttribute('aria-expanded', open && b.dataset.megaGroup === key ? 'true' : 'false');
        });
        panel.querySelectorAll('[data-mega-section]').forEach(function (sec) {
            sec.classList.toggle('is-target', open && sec.dataset.megaSection === key);
        });
    }
    buttons.forEach(function (b) {
        b.addEventListener('mouseenter', function () { setOpen(true, b.dataset.megaGroup); });
        b.addEventListener('focus', function () { setOpen(true, b.dataset.megaGroup); });
    });
    header.addEventListener('mouseleave', function () { setOpen(false); });
    header.addEventListener('focusout', function (e) {
        if (!header.contains(e.relatedTarget)) { setOpen(false); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.classList.contains('d-none')) { setOpen(false); }
    });
})();;
</script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
