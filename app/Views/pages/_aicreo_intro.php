<?php
/**
 * AICreo 소개 — 관리자에서 슬러그 home 페이지를 저장하지 않았을 때 홈이 보여주는 기본 화면.
 * 색은 모두 var(--토큰)이라 테마가 덮어쓴다. 수치·고객 사례는 넣지 않는다(저장소에 없는 사실).
 *
 * @var array<string, mixed> $settings
 * @var list<array{q: string, a: string}> $faqs
 */
$repoUrl = 'https://github.com/aivance-kr/AICreo';

$principles = [
    ['icon' => 'bi-lightning-charge', 'title' => '납품 속도', 'desc' => '저장소를 복제해 설정과 테마만 입히면 됩니다. 단순 홈페이지 3~5일, 중형 사이트 1~2주를 목표로 설계했습니다.'],
    ['icon' => 'bi-person-check',     'title' => '운영 자립', 'desc' => '공지, 페이지 문구, 배너·팝업, 문의 답장까지 관리자 화면에서 끝납니다. 개발자를 다시 부르지 않아도 됩니다.'],
    ['icon' => 'bi-palette',          'title' => '테마 교체', 'desc' => '코어는 그대로 두고 껍데기만 바꿉니다. ZIP 업로드 후 클릭 한 번으로 전환하고, 빠진 화면은 기본 테마가 채웁니다.'],
];

$features = [
    ['icon' => 'bi-journals',        'title' => '게시판 CMS',      'desc' => '게시판별 읽기·쓰기 권한, 위지윅 에디터, 첨부, 댓글, 비밀글, 카테고리, 검색.'],
    ['icon' => 'bi-window-stack',    'title' => '동적 페이지·메뉴', 'desc' => '슬러그 기반 페이지와 2단계 드래그 메뉴. 홈 화면도 관리자에서 직접 편집합니다.'],
    ['icon' => 'bi-envelope-paper',  'title' => '문의·알림',         'desc' => '문의폼은 DB에 저장되고 이메일로 전달되며, Slack 알림과 스팸 방지가 붙어 있습니다.'],
    ['icon' => 'bi-badge-ad',        'title' => '배너·팝업·광고',    'desc' => '위치별 배너, 기간·범위 지정 팝업, 애드센스용 광고 슬롯을 기간 기반으로 자동 노출합니다.'],
    ['icon' => 'bi-search',          'title' => 'SEO·GEO',          'desc' => 'sitemap, robots, llms.txt, JSON-LD, RSS, IndexNow 자동 색인 제출을 기본 제공합니다.'],
    ['icon' => 'bi-pencil-square',   'title' => '블로그 기능',       'desc' => '시리즈, 태그, 월별 아카이브, 예약 발행, 뉴스레터, Groq 기반 요약·태그·제목 제안.'],
    ['icon' => 'bi-graph-up',        'title' => '통계·보안',         'desc' => '접속 통계와 글별 조회수, 404 반복 IP 자동 차단, 백업·복원까지 관리자에서 다룹니다.'],
    ['icon' => 'bi-people',          'title' => '회원·소셜 로그인',  'desc' => '회원가입 온/오프, Google·Naver·Kakao 로그인을 제공자별 스위치로 제어합니다.'],
];

$steps = [
    ['title' => '복제',    'desc' => '저장소를 클라이언트용으로 복제하고 .env 를 채워 마이그레이션합니다.'],
    ['title' => '테마',    'desc' => '샘플 테마를 고르거나 토큰과 CSS 만 덮어 클라이언트 얼굴을 입힙니다.'],
    ['title' => '설정',    'desc' => '사이트 정보, 메뉴, 게시판, 페이지를 관리자 화면에서 채웁니다.'],
    ['title' => '배포',    'desc' => 'main 에 반영되면 서버에서 코드 갱신과 마이그레이션이 자동으로 돕니다.'],
];
?>
<!-- 히어로 -->
<section class="intro-hero" aria-labelledby="intro-title">
    <div class="container">
        <p class="intro-eyebrow">CodeIgniter 4 홈페이지 보일러플레이트</p>
        <h1 id="intro-title" class="intro-title">코어는 재사용하고,<br>껍데기만 교체합니다.</h1>
        <p class="intro-lead">
            <?= esc($settings['site_name'] ?? 'AICreo') ?>는 1인 웹에이전시가 클라이언트 홈페이지를 빠르게 납품하도록 만든 템플릿입니다.
            게시판 CMS, 동적 페이지, 문의, 회원, 마케팅 오버레이, SEO 를 갖춘 채로 시작합니다.
        </p>
        <div class="intro-actions">
            <a href="/contact" class="btn btn-light btn-lg">도입 문의하기</a>
            <a href="<?= esc($repoUrl) ?>" class="btn btn-outline-light btn-lg" target="_blank" rel="noopener">
                <i class="bi bi-github me-1" aria-hidden="true"></i>GitHub 에서 보기<span class="visually-hidden"> (새 창 열림)</span>
            </a>
        </div>
        <ul class="intro-badges" aria-label="기술 사양">
            <li>PHP 8.5</li>
            <li>CodeIgniter 4</li>
            <li>MIT 라이선스</li>
            <li>KWCAG 2.2 AA 기준</li>
        </ul>
    </div>
</section>

<!-- 세 가지 약속 -->
<section class="intro-section" aria-labelledby="intro-principles">
    <div class="container">
        <h2 id="intro-principles" class="intro-heading">납품하는 쪽도, 운영하는 쪽도 덜 일하게</h2>
        <div class="row g-4">
            <?php foreach ($principles as $item): ?>
            <div class="col-md-4">
                <div class="intro-card h-100">
                    <i class="bi <?= esc($item['icon']) ?> intro-card-icon" aria-hidden="true"></i>
                    <h3 class="intro-card-title"><?= esc($item['title']) ?></h3>
                    <p class="intro-card-desc"><?= esc($item['desc']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 기능 -->
<section class="intro-section intro-section-alt" aria-labelledby="intro-features">
    <div class="container">
        <h2 id="intro-features" class="intro-heading">처음부터 갖춰진 것들</h2>
        <div class="row g-3">
            <?php foreach ($features as $item): ?>
            <div class="col-sm-6 col-lg-3">
                <div class="intro-card intro-card-flat h-100">
                    <i class="bi <?= esc($item['icon']) ?> intro-card-icon" aria-hidden="true"></i>
                    <h3 class="intro-card-title"><?= esc($item['title']) ?></h3>
                    <p class="intro-card-desc"><?= esc($item['desc']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 납품 흐름 -->
<section class="intro-section" aria-labelledby="intro-flow">
    <div class="container">
        <h2 id="intro-flow" class="intro-heading">납품은 네 걸음</h2>
        <ol class="intro-steps">
            <?php foreach ($steps as $item): ?>
            <li>
                <strong class="intro-step-title"><?= esc($item['title']) ?></strong>
                <span class="intro-card-desc"><?= esc($item['desc']) ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<!-- 접근성·품질 -->
<section class="intro-section intro-section-alt" aria-labelledby="intro-quality">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6">
                <h2 id="intro-quality" class="intro-heading text-lg-start">접근성은 옵션이 아니라 기본값</h2>
                <p class="intro-card-desc mb-0">
                    공공·공공성 기관 납품을 전제로 KWCAG 2.2 AA 를 계약 조건처럼 다룹니다.
                    대체 텍스트, 키보드 조작, 건너뛰기 링크, 초점 표시, 명도 대비 4.5:1 을 코어와 기본 테마가 먼저 지킵니다.
                </p>
            </div>
            <div class="col-lg-6">
                <ul class="intro-checks">
                    <li>명도 대비를 실측해 토큰마다 기록</li>
                    <li>폼 레이블·오류 안내·상태 메시지 연결</li>
                    <li>CSRF, 입력 검증, 바인딩 쿼리, 반복 404 IP 자동 차단</li>
                    <li>PHPStan 레벨 6 과 PHPUnit 을 통과해야 배포</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- 자주 묻는 질문 (FAQPage JSON-LD 와 같은 원문) -->
<?php if (! empty($faqs)): ?>
<section class="intro-section" aria-labelledby="intro-faq">
    <div class="container">
        <h2 id="intro-faq" class="intro-heading">자주 묻는 질문</h2>
        <dl class="intro-faq">
            <?php foreach ($faqs as $faq): ?>
            <dt class="intro-card-title"><?= esc($faq['q']) ?></dt>
            <dd class="intro-card-desc"><?= esc($faq['a']) ?></dd>
            <?php endforeach; ?>
        </dl>
    </div>
</section>
<?php endif; ?>

<!-- CTA -->
<section class="intro-cta">
    <div class="container">
        <h2 class="intro-cta-title">다음 프로젝트를 AICreo 로 시작해 보세요</h2>
        <p class="intro-cta-desc">궁금한 점을 남겨주시면 담당자가 직접 확인 후 연락드립니다.</p>
        <?php if (! empty($settings['phone'])): ?>
            <a href="tel:<?= esc($settings['phone']) ?>" class="btn btn-outline-light me-2">
                <i class="bi bi-telephone me-1" aria-hidden="true"></i><span class="visually-hidden">전화 </span><?= esc($settings['phone']) ?>
            </a>
        <?php endif; ?>
        <a href="/contact" class="btn btn-primary">온라인 문의</a>
    </div>
</section>
