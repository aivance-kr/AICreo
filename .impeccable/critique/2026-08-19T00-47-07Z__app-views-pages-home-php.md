---
target: 메인화면 (app/Views/pages/home.php)
total_score: 16
max_score: 20
na_heuristics: 3,5,7,9,10
p0_count: 0
p1_count: 2
timestamp: 2026-08-19T00-47-07Z
slug: app-views-pages-home-php
---
Method: dual-agent (A: 디자인 리뷰 서브에이전트 · B: 디텍터/브라우저 증거 서브에이전트)

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3/4 | 활성 메뉴 밑줄은 있으나 스크롤/섹션 전환 피드백 없음 |
| 2 | Match System / Real World | 3/4 | 카피가 업종 불문 placeholder 톤 |
| 3 | User Control and Freedom | n/a | 랜딩페이지 특성상 해당 없음 |
| 4 | Consistency and Standards | 4/4 | Bootstrap 표준·토큰 시스템 전반에 일관 |
| 5 | Error Prevention | n/a | 이 화면엔 입력 폼 없음 |
| 6 | Recognition Rather Than Recall | 3/4 | 서비스 카드 3개 설명문이 서로 구분되는 정보가 아님 |
| 7 | Flexibility and Efficiency | n/a | Persuade 모드 — 해당 없음 |
| 8 | Aesthetic and Minimalist Design | 3/4 | 5개 섹션이 같은 밀도·같은 무게로 나열 |
| 9 | Error Recovery | n/a | 오류 상태 발생 여지 없음 |
| 10 | Help and Documentation | n/a | Persuade 모드 — 해당 없음 |
| **Total** | | **16/20** | **Good (80%)** |

## Design Specificity Verdict

디자인 리뷰(A): Bootstrap 5 공식 예제 그대로인 조합(단색 히어로, 카드 3열, 다크 CTA/푸터). DESIGN.md "백지" 원칙의 의도된 결과이나 정보구조·카피·전환 설계 자체가 템플릿 수준에 머묾.

디텍터 스캔(B): broken-image 경고 2건은 PHP 헬퍼 함수 줄을 오탐한 false positive로 확인. 실질 결함 0건.

시각 확인: 영구 오버레이는 주입하지 않음(탭 종료). B가 대비비·heading 순서·클릭 타깃 크기를 직접 계측.

## Overall Impression

접근성 배관과 토큰 아키텍처는 탄탄하나 콘텐츠 설계(카피 차별화, empty state, CTA 반복)는 손대지 않은 Bootstrap 스타터 그대로. 가장 큰 기회는 게시글 0건 상태(첫 배포 직후, 가장 흔한 실제 상태)를 위한 설계가 전무하다는 점.

## What's Working

1. 접근성이 사후 감사 항목이 아니라 구조에 있다(aria-current, 새 창 안내, 건너뛰기 링크, 배너 CLS 방지).
2. 토큰 아키텍처가 제품 목적에 정확히 복무한다(home.php 헥사 하드코딩 0건, color-mix() hover가 테마 변경을 자동 추종).
3. 정보 위계가 명료하다(h1 하나, 이후 전부 h2 시맨틱 유지, 시각 크기만 클래스 조정).

## Priority Issues

[P1] 히어로 보조 버튼("서비스 보기") 명도 대비 실측 4.27:1 — KWCAG/WCAG AA 4.5:1 미달. 기본 파랑(#0d6efd) 자체에서 이미 실패하는 구조적 문제. Fix: 텍스트를 순백으로 올리거나 버튼 배경을 더 어둡게. Suggested command: /impeccable harden

[P1] 게시글 0건 시 "최신 공지" 섹션이 !empty($latestPosts) 가드로 통째로 사라짐 — 신규 납품 직후 가장 흔한 상태에 대한 empty state 설계가 없음. PRODUCT.md의 "운영 자립" 원칙과 정면 배치. Fix: 최소 placeholder 또는 "첫 공지를 등록하세요" 관리자 유도 CTA. Suggested command: /impeccable onboard

[P2] 히어로·하단 CTA가 같은 목적지(/contact)로 실질 중복, 두 번째가 첫 번째를 강화하지 못함. Fix: 하단 카피에 새로운 확신 요소(응답시간·절차) 추가. Suggested command: /impeccable clarify

[P2] 전화 CTA가 모바일 내비게이션에서 완전히 사라짐(d-none d-lg-inline-flex, 992px 미만). Fix: 오프캔버스 메뉴에도 전화 버튼 유지. Suggested command: /impeccable adapt

[P3] 서비스 카드 3개 설명문이 상호 차별화되지 않음(추상적 형용사 나열). Fix: 카드마다 구체적 산출물/수치로 차별화. Suggested command: /impeccable clarify

## Persona Red Flags

Jordan(첫 방문자): 히어로만으로 업종을 알 수 없음, 서비스 섹션까지 스크롤해야 도메인 드러남.
Sam(접근성 의존 사용자): 실측 4.27:1 대비 버튼은 저시력 사용자에게 실제로 읽기 어려움(추정 아닌 계측 사실).
Casey(모바일 사용자): 즉시 전화 버튼이 내비게이션에 없음(992px 미만 숨김), CTA 섹션까지 스크롤 필요.

## Minor Observations

- bg-primary 순수 파랑 위에 이미 대비 미달 — 테마가 더 밝은 색으로 바꾸면 악화 가능.
- 배너 슬롯 최대폭(1000px)과 섹션 .container 폭이 미세하게 달라 정렬 어긋남.
- 서비스 카드 아이콘 text-primary 고정 — 카드 배경이 흰색일 때만 전제.
- 375px 정확한 뷰포트 실측은 이번 세션 브라우저 자동화 도구 한계로 미확보(도달 가능한 최소 500px 기준 가로 스크롤 없음 확인).

## Questions to Consider

1. "백지" 철학이 색·형태 중립인가, 카피·정보구조까지 포함하는가?
2. 게시글 0건 상태를 깨끗한 empty state로 볼 것인가, 운영 자립 원칙 위반으로 볼 것인가?
3. 히어로·CTA 동일 목적지 반복이 의도된 설계인가, 이어붙인 결과인가?
