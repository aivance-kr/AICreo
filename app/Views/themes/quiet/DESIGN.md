# Theme Design Brief: quiet (조용한 목록)

> 이 테마(`app/Views/themes/quiet/`, `public/themes/quiet/`) 전용 디자인 메모. 코어 시스템은 루트 [`/DESIGN.md`](../../../../DESIGN.md).
> 구글 애드센스 광고 게재를 전제로 한 «블로그형» 테마 4종 중 하나다(`ilgan`·`cobalt`·`quiet`·`devnotes`).

- **성격**: 가독성 최우선 미니멀 리스트. 가장 무난한 애드센스 구성.
- **색**: 그린 `#0a7a4a`(흰 배경 5.6:1), 흰 바탕
- **글꼴**: Pretendard
- **레이아웃**: 고정 헤더(scroll-padding 확보) + 목록형 본문 + 우측 광고 사이드바
- **홈**: 글 제목·요약 2줄·우측 정사각 썸네일, 3~4개마다 인피드 광고

## 광고 슬롯 규칙(4종 공통)

- 위치: 홈 상단 · 홈 목록 중간(`home_feed`) · 홈 하단 · 게시글 본문 상/하단 · 사이드바(`sidebar`). 관리자 `광고 관리`에서 위치별로 스니펫 등록.
- 모든 슬롯은 `themes/default/components/ad_unit.php` 가 그린다 — «광고» 라벨 고정, 슬롯별 최소 높이 예약(CLS 방지), 미채움(unfilled) 슬롯은 접힘. 등록된 광고가 없으면 아무것도 그리지 않는다.
- 사이드바 광고는 로그인·글쓰기·수정 같은 게시자 콘텐츠가 없는 화면에는 싣지 않는다(`BaseController`).
- 광고는 메뉴·버튼·링크와 떨어진 곳에만 둔다(실수 클릭 방지).

## 구조

- 레이아웃은 접근성 계약(토큰 CSS → skip-link → `<main id="main-content">`)을 그대로 유지한다.
- 메뉴·사이드바·푸터 정보는 `themes/default/components/{menu_items,blog_sidebar,site_footer_info}.php` 를 공유한다.
- 게시판·인증 뷰는 테마 영향을 받지 않으므로 CSS(`.{접두사}-main …`)로만 맞춘다.

## Unresolved

- 썸네일 이미지(`thumbnail.png`) 미제작 — 관리자 테마 카드는 이미지 없이 표시된다.
- 게시글 본문 중간(인아티클) 광고는 에디터 HTML 을 건드려야 해서 미지원. 본문 상·하단 슬롯만 제공.
