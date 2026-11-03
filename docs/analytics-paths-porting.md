# 이동 경로 통계 이식 가이드 (AICreo → aicopia 등)

접속 후 이동 경로(진입·이탈·페이지 간 이동·자주 걷는 경로·특정 페이지의 직전/직후)를 보여주는 기능이다.
**스키마 변경이 없다** — 기존 `page_visits` 테이블(`uri, ip_address, user_agent, created_at, is_not_found`)만 쓰고, 과거 데이터도 바로 분석된다.

## 동작 방식
- 방문자 = `IP + User-Agent` (쿠키·JS 불필요), 세션 = 같은 방문자의 요청 간격이 30분 미만인 연속 방문. 새로고침(연속 같은 주소)은 합친다.
- 분석은 `PathAnalyzer`(순수 PHP, DB·프레임워크 비의존)가 한다. 방문 행 배열 → 세션 → 집계.
- 컨트롤러가 기간(7/30/90일) 방문 행을 최대 5만 건 읽어 분석하고 10분 캐시한다. 넘치면 화면에 안내.

## 옮길 파일
| 파일 | 할 일 |
|------|-------|
| `app/Libraries/PathAnalyzer.php` | 네임스페이스만 바꿔 그대로 복사 (`tests/unit/PathAnalyzerTest.php` 도 함께) |
| `app/Models/PageVisitModel.php` → `visitsForPaths()` | 대상 프로젝트 방문 모델에 메서드 추가. `countedOnly()` 가 없으면 404/차단 IP 필터 줄을 제거하거나 동등하게 구현 |
| `app/Controllers/Admin/StatsController.php` → `paths()` | 메서드 복사 (`dayRange()`·`POST_STATS_*` 상수 의존) |
| `app/Views/admin/stats/paths.php` | 레이아웃(`layouts/admin`)·Bootstrap 5 전제. 다른 UI 면 `$result` 배열만 렌더하면 된다 |
| `app/Config/Routes.php` | `stats/paths` GET 라우트 (admin 그룹 안) |

## 전제 조건
- `page_visits` 와 같은 컬럼을 가진 방문 로그 + 크롤러 제외 기록 필터(`VisitLogFilter`).
- `created_at` 인덱스(있음). 방문이 많은 사이트는 `visitsForPaths` 의 `$limit` 과 캐시 TTL 을 조정.

## 확장 여지
- 정확도를 더 높이려면 방문자 쿠키(`visitor_id`)·세션 컬럼을 `page_visits` 에 추가하고 `buildSessions()` 의 방문자 키만 교체하면 된다. `analyze()` 는 그대로 쓴다.
