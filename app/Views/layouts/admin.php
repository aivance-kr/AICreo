<?php
$uri = uri_string();

// 사이드바 활성 항목 판정 — 설정 3종은 접두어가 겹치므로 먼저 갈라낸다.
$isThemeSettings   = str_starts_with($uri, 'admin/settings/theme');
$isOauthSettings   = str_starts_with($uri, 'admin/settings/oauth');
$isGeneralSettings = str_starts_with($uri, 'admin/settings') && ! $isThemeSettings && ! $isOauthSettings;
$isDashboard       = $uri === 'admin' || $uri === 'admin/dashboard';
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
    <link rel="stylesheet" href="/css/tokens.css">
    <style>
        body  { background: var(--surface-body); font-family: var(--font-sans); color: var(--text-body); }

        /* Bootstrap 오프캔버스가 992px 이상에서 배경을 transparent !important 로 강제 리셋한다.
           #sidebar 는 ID 선택자라 특정성은 더 높지만 !important 없이는 그 규칙을 이길 수 없다 —
           그래서 여기도 !important 로 맞춰 특정성 비교(ID > class)로 승부를 낸다. */
        #sidebar { width: var(--sidebar-w); background: var(--dark) !important; }
        /* 어두운 표면 위에서는 초점 링을 밝은 쪽으로 뒤집는다 */
        #sidebar { --focus-ring-color: var(--on-dark-strong); }
        #sidebar .brand { padding: 1rem 1.2rem; color: var(--on-dark-strong); font-weight: 700; font-size: 1.1rem; border-bottom: 1px solid var(--on-dark-raised); }
        #sidebar .nav-link { color: var(--on-dark-body); padding: .6rem 1.2rem; font-size: .875rem; }
        #sidebar .nav-link:hover,
        #sidebar .nav-link.active { color: var(--on-dark-strong); background: var(--on-dark-raised); }
        #sidebar .nav-section { color: var(--on-dark-muted); font-size: .7rem; padding: .8rem 1.2rem .2rem; letter-spacing: .06em; text-transform: uppercase; }
        /* Bootstrap .text-danger(#dc3545)는 사이드바 배경 위에서 3.2:1 로 AA 미달이었다 —
           어두운 표면 전용 danger 톤(5.2:1)으로 이 컨테이너 안에서만 되돌린다.
           .text-danger 자체가 !important 라 특정성이 아니라 !important 로만 이길 수 있다. */
        #sidebar .nav-link.text-danger { color: var(--on-dark-danger) !important; }
        #sidebar .nav-link.text-danger:hover { color: var(--on-dark-strong) !important; }

        #topbar { background: var(--surface-raised); border-bottom: 1px solid var(--border-subtle); padding: .6rem 1rem; position: sticky; top: 0; z-index: 100; }
        #content { padding: 1.25rem 1rem; }

        /* 데스크톱에서만 사이드바를 고정 배치한다.
           그 아래 폭에서는 Bootstrap 오프캔버스가 맡으므로 여백을 주지 않는다. */
        @media (min-width: 992px) {
            #sidebar { position: fixed; top: 0; left: 0; min-height: 100vh; }
            #topbar  { margin-left: var(--sidebar-w); padding: .6rem 1.5rem; }
            #content { margin-left: var(--sidebar-w); padding: 1.5rem; }
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

<div class="offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
    <div class="offcanvas-header d-lg-none">
        <span class="offcanvas-title h6 mb-0 text-white" id="sidebarLabel">관리자 메뉴</span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="메뉴 닫기"></button>
    </div>

    <div class="offcanvas-body p-0 d-block">
        <div class="brand"><i class="bi bi-grid-3x3-gap-fill me-2" aria-hidden="true"></i><?= esc($settings['site_name'] ?? 'Admin') ?></div>
        <nav class="mt-2" aria-label="관리자 메뉴">
            <div class="nav-section">콘텐츠</div>
            <a href="/admin/dashboard" class="nav-link <?= $isDashboard ? 'active' : '' ?>" <?= $isDashboard ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>대시보드
            </a>
            <a href="/admin/pages" class="nav-link <?= str_starts_with($uri, 'admin/pages') ? 'active' : '' ?>" <?= str_starts_with($uri, 'admin/pages') ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-file-text me-2" aria-hidden="true"></i>페이지 관리
            </a>
            <a href="/admin/boards" class="nav-link <?= str_starts_with($uri, 'admin/boards') ? 'active' : '' ?>" <?= str_starts_with($uri, 'admin/boards') ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-card-list me-2" aria-hidden="true"></i>게시판 관리
            </a>
            <a href="/admin/posts" class="nav-link <?= str_starts_with($uri, 'admin/posts') ? 'active' : '' ?>" <?= str_starts_with($uri, 'admin/posts') ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-file-earmark-text me-2" aria-hidden="true"></i>전체 게시물
            </a>
            <a href="/admin/media" class="nav-link <?= str_starts_with($uri, 'admin/media') ? 'active' : '' ?>" <?= str_starts_with($uri, 'admin/media') ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-images me-2" aria-hidden="true"></i>미디어
            </a>
            <a href="/admin/banners" class="nav-link <?= str_starts_with($uri, 'admin/banners') ? 'active' : '' ?>" <?= str_starts_with($uri, 'admin/banners') ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-image me-2" aria-hidden="true"></i>배너 관리
            </a>
            <a href="/admin/popups" class="nav-link <?= str_starts_with($uri, 'admin/popups') ? 'active' : '' ?>" <?= str_starts_with($uri, 'admin/popups') ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-window me-2" aria-hidden="true"></i>팝업 관리
            </a>

            <div class="nav-section">운영</div>
            <a href="/admin/users" class="nav-link <?= str_starts_with($uri, 'admin/users') ? 'active' : '' ?>" <?= str_starts_with($uri, 'admin/users') ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i>회원 관리
            </a>
            <a href="/admin/inquiries" class="nav-link <?= str_starts_with($uri, 'admin/inquiries') ? 'active' : '' ?>" <?= str_starts_with($uri, 'admin/inquiries') ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-envelope me-2" aria-hidden="true"></i>문의 수신함
                <?php if ($unreadInquiries > 0): ?>
                <span class="badge bg-danger ms-1"><?= esc((string) $unreadInquiries) ?><span class="visually-hidden">건의 읽지 않은 문의</span></span>
                <?php endif; ?>
            </a>
            <a href="/admin/menus" class="nav-link <?= str_starts_with($uri, 'admin/menus') ? 'active' : '' ?>" <?= str_starts_with($uri, 'admin/menus') ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-list-ul me-2" aria-hidden="true"></i>메뉴 관리
            </a>

            <div class="nav-section">설정</div>
            <a href="/admin/settings/general" class="nav-link <?= $isGeneralSettings ? 'active' : '' ?>" <?= $isGeneralSettings ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-gear me-2" aria-hidden="true"></i>사이트 설정
            </a>
            <a href="/admin/settings/theme" class="nav-link <?= $isThemeSettings ? 'active' : '' ?>" <?= $isThemeSettings ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-palette me-2" aria-hidden="true"></i>테마 관리
            </a>
            <a href="/admin/settings/oauth" class="nav-link <?= $isOauthSettings ? 'active' : '' ?>" <?= $isOauthSettings ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-people me-2" aria-hidden="true"></i>소셜 로그인
            </a>

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

<div id="topbar" class="d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center">
        <button class="btn btn-sm btn-outline-secondary d-lg-none me-2" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="관리자 메뉴 열기">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <h1 class="h6 mb-0 text-dark"><?= esc($pageTitle ?? '관리자') ?></h1>
    </div>
    <span class="text-muted small"><i class="bi bi-person-circle me-1" aria-hidden="true"></i><?= esc($authUser['nickname']) ?></span>
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
<?= $this->renderSection('scripts') ?>
</body>
</html>
