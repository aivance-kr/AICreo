---
target: 운영자 화면 (app/Views/admin/dashboard/index.php)
total_score: 27
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 2
timestamp: 2026-08-19T01-16-57Z
slug: app-views-admin-dashboard-index-php
---
Method: dual-agent (A: 디자인 리뷰 서브에이전트 · B: 디텍터/브라우저 증거 서브에이전트)

부록: B가 이 화면과 무관한 별도 버그를 발견함 — app/Config/App.php 의 $baseURL 이
http://localhost:8080/ 로 하드코딩돼 있고 .env 오버라이드 없음. 8080 아닌 포트/도메인에서
CI4 redirect()·디버그바가 계속 8080 절대경로를 생성해 CORS 에러·오배송 리다이렉트 발생.
디자인 범위 밖이라 별도 이슈로 다룰 것을 권장.

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3/4 | 카운트는 즉시 보이나 갱신 시각·운영 상태 신호 없음 |
| 2 | Match System / Real World | 3/4 | 라벨은 자연스러우나 아이콘이 범용이라 도메인 신호 없음 |
| 3 | User Control and Freedom | 3/4 | 목록에서 즉시 처리 불가, 상세로 나가야 함 |
| 4 | Consistency and Standards | 3/4 | 카드 언어는 일관되나 컴포넌트 어휘 미정의 |
| 5 | Error Prevention | 4/4 | 읽기 전용, 파괴적 액션 없음 |
| 6 | Recognition Rather Than Recall | 4/4 | 라벨·아이콘·값이 한 화면에 모두 노출 |
| 7 | Flexibility and Efficiency | 1/4 | 매일 방문 화면인데 원클릭 진입점 전무 |
| 8 | Aesthetic and Minimalist Design | 4/4 | 장식 없이 톤 유지 |
| 9 | Error Recovery | 2/4 | empty state 문구는 있으나 다음 행동 유도 없음 |
| 10 | Help and Documentation | 0/4 | 매뉴얼 경로 어디에도 없음(직접 확인) |
| **Total** | | **27/40** | **Acceptable (68%)** |

## Design Specificity Verdict

디자인 리뷰(A): 아이콘-in-tint박스 통계 카드 4개+list-group 2단은 CoreUI/AdminLTE 계열 첫 화면과 동일. AICreo 고유 운영 신호(테마 상태, 납품 단계, SEO/GEO 색인) 전무.

디텍터 스캔(B): design-system-font-size advisory 2건(admin.php:34,35, 기존 값). dashboard/index.php 클린.

시각 확인: 로그인 후 실제 렌더링 확인(통계 카드 4개, 최근 문의/게시글 2단). 영구 오버레이 미주입.

## Overall Impression

접근성 배관은 탄탄하나 정보 우선순위 설계가 없음 — 조치 필요한 것과 참고용 숫자가 동일 무게. 매뉴얼 링크 부재가 "운영자가 개발자 없이 끝낸다"는 제품 원칙을 정면 배신.

## What's Working

1. 문의 우선순위 데이터 모델 존재(미읽음 카운트+NEW 배지) — 표현만 고치면 됨.
2. 최근 게시글 목록의 실용적 디테일(배지+truncate+새창 링크).
3. 관리자 전체와 톤 일관.

## Priority Issues

[P1] 매뉴얼로 가는 경로가 어디에도 없음 — docs/manual.md 가 이 페르소나를 위해 존재한다고 PRODUCT.md 명시하지만 대시보드/사이드바/상단바 어디서도 도달 불가(grep 확인). Fix: 사이드바 하단/상단바에 도움말 링크 추가. Suggested command: /impeccable clarify

[P1] 사이드바 "로그아웃" 링크 명도 대비 실측 3.21:1 — KWCAG/WCAG AA 4.5:1 미달(.text-danger on 다크 사이드바). Fix: on-dark 토큰 계열로 danger 톤 조정. Suggested command: /impeccable harden

[P2] 파워유저/반복 방문자 단축 진입점 부재(heuristic 7=1/4) — 매일 방문 화면인데 원클릭 작성 진입점 없음. Fix: 자주 쓰는 작성 액션 바로가기 추가. Suggested command: /impeccable layout

[P2] 시각 계층이 긴급도 미반영 — 미읽음 문의와 참고용 총계가 동일 무게. Fix: 미읽음 문의 카드 강조 톤 차별화. Suggested command: /impeccable layout

[P2] Heading 순서 위반 — 오프캔버스 제목(H2)이 본문 제목(H1)보다 DOM에서 먼저 등장(실측 확인). DESIGN.md Level-Is-Meaning Rule 위반. Fix: 오프캔버스 제목 레벨 조정. Suggested command: /impeccable harden

## Persona Red Flags

미영(비개발자 운영자, 실제 주 사용자): "오늘 뭘 해야 하지" 답이 없고 매뉴얼 링크도 없음.
Alex(파워유저): 매 방문마다 사이드바 재탐색 필요.
Sam(접근성 의존 사용자): 로그아웃 링크 3.21:1(계측 사실), 카드 hover 피드백 없음.

## Minor Observations

- bg-<색> 하드코딩이 대시보드만 Theme Override Rule 위반(테마 primary 변경 시 안 따라감).
- 절대 날짜만 표시, 상대 시간 없음.
- "전체보기" 링크 51.5×24.0px, KWCAG 최소 타깃(24×24)에 정확히 걸침.
- 사이드바 링크 15개 높이 40.2px, KWCAG 24px는 충족하나 44px 권장치 미달.

## Questions to Consider

1. 통계 카드 4개가 사라져도 운영자 실제 일과가 어려워지는가, 그냥 표지인가?
2. 매뉴얼 찾기에 파일탐색이 필요하다면 PRODUCT.md가 결함이라 부르는 상황과 같은 것 아닌가?
3. 미읽음 문의가 3곳에서 표시되는데 왜 대시보드 본문에선 총계와 동일한 무게로 그려지는가?
