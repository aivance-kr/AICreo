# AICreo 개발 로드맵

## 1. 배너 관리 ✅ 완료

### 등록 항목
- 배너 이미지 업로드 (jpg, jpeg, png, gif / 최대 2MB)
- 시작/종료 날짜 설정
- 운영/미운영 상태 (수동 처리)
- 배너 위치: 메인 상단, 메인 하단, 서브 좌측, 서브 우측
- 링크 URL + 열기 방식 (새 창 / 현재 창)
- 노출 우선순위 번호 (숫자가 낮을수록 우선)

### 리스트 표시 항목
- 썸네일 이미지
- 운영 중: 초록 뱃지 / 미운영: 회색 뱃지
- 시작/종료일
- 위치
- 우선순위

### 기술 사항
- 메인 배너 가로: 1000px (CSS max-width: 100% 반응형)
- 서브 배너 가로: 300px / `.sp-banner-slot` 내부 `<a>` 태그로 삽입 (최대 3개)
- 서브 좌측 배너: 모든 프론트 페이지 레이아웃에 고정 노출
- 메인 배너: 홈(/) 페이지 전용
- 종료일 지난 배너: 프론트에서 자동 숨김 (날짜 조건 쿼리)

### 구현 파일
| 파일 | 설명 |
|---|---|
| `app/Database/Migrations/2024-01-01-000007_CreateBannersTable.php` | banners 테이블 |
| `app/Models/BannerModel.php` | 배너 모델 |
| `app/Libraries/ImageUploader.php` | 이미지 업로드 처리 (`new ImageUploader('banners')`) |
| `app/Controllers/Admin/BannerController.php` | 관리자 CRUD |
| `app/Controllers/BaseController.php` | `$subLeftBanners` 전역 주입 (admin 경로 제외) |
| `app/Controllers/Front/HomeController.php` | `$mainTopBanners`, `$mainBotBanners` 홈 전달 |
| `app/Views/admin/banners/list.php` | 관리자 목록 뷰 |
| `app/Views/admin/banners/form.php` | 등록/수정 폼 |
| `app/Views/themes/default/components/banner_slot.php` | 메인 배너 렌더링 컴포넌트 |
| `app/Views/themes/default/layouts/main.php` | default 레이아웃 서브 좌측 배너 슬롯 |

### 테마별 추가 구현 파일 (spring / dark)
| 파일 | 설명 |
|---|---|
| `app/Views/themes/spring/layouts/main.php` | 홈/서브 조건 분기, 서브 좌측 배너 사이드바 |
| `app/Views/themes/spring/pages/home.php` | 메인 상단/하단 배너 섹션 포함 홈 뷰 |
| `public/themes/spring/css/style.css` | `.sp-main-banner-*`, `.sp-banner-img` 스타일 |
| `dark.zip / views/layouts/main.php` | 서브 좌측 배너 사이드바(`dk-layout`) |
| `dark.zip / views/pages/home.php` | 메인 상단/하단 배너 섹션 포함 홈 뷰 |
| `dark.zip / public/css/style.css` | `dk-*` 레이아웃/배너 스타일 추가 |

### 주요 트러블슈팅
| 항목 | 원인 | 해결 |
|---|---|---|
| 서브 좌측 배너 미표시 | spring 테마 layouts에 하드코딩된 플레이스홀더 | DB 데이터 기반 렌더링으로 교체 |
| 배너 시간 조건 오작동 | PHP 서버 UTC, 사용자 입력 KST | `App.php` `appTimezone = 'Asia/Seoul'` 설정 |
| CI4 NULL 조건 쿼리 오류 | `groupStart/orWhere` NULL 호환 이슈 | raw where 문자열로 변경 |
| 홈 리디자인 후 서브 배너 소실 | 레이아웃 전체에서 사이드바 제거 | `uri_string()` 조건으로 홈만 제외 |

---

## 2. 팝업 관리 ✅ 완료

### 등록 항목
- 제목
- 이미지 업로드 (jpg, jpeg, png, gif / 최대 2MB)
- 텍스트 본문 (WYSIWYG 에디터)
- 시작/종료일 설정
- 운영/미운영 상태 (수동 처리)
- 노출 위치: 전체(메인+서브) / 메인 전용 / 특정 페이지 (메뉴 목록에서 복수 선택)
- 화면 표시 좌표 (X, Y — left/top px 입력)
- 우선순위 번호 (숫자가 낮을수록 우선, 동일 위치 팝업 겹침 방지용)

### 리스트 표시 항목
- 썸네일 미리보기
- 운영 중: 초록 뱃지 / 미운영: 회색 뱃지
- 노출 위치
- 표시 좌표 (X, Y)
- 우선순위
- 시작/종료일

### 기술 사항
- 팝업 크기: 이미지 비율 자동 (width 고정, height auto)
- 동일 페이지에 복수 팝업: 우선순위 순으로 정렬 후 좌표 기반 개별 표시 (겹침 없음)
- 닫기 동작: X 버튼 즉시 닫기 + "오늘 하루 보지 않기" (쿠키 24h, 체크 시 즉시 닫힘)
- 특정 페이지 지정: `menus` 테이블 기반 체크박스 다중 선택 → `popup_pages` 중간 테이블
- 좌표 기본값: X=20, Y=20 (미입력 시)

### 구현 파일
| 파일 | 설명 |
|---|---|
| `app/Database/Migrations/2024-01-01-000008_CreatePopupsTable.php` | popups + popup_pages 테이블 |
| `app/Models/PopupModel.php` | 모델, `getActiveForPage()`, `syncPages()` (트랜잭션) |
| `app/Libraries/ImageUploader.php` | 이미지 업로드 (`new ImageUploader('popups')`) |
| `app/Controllers/Admin/PopupController.php` | 관리자 CRUD |
| `app/Controllers/BaseController.php` | `$activePopups` 전역 주입 |
| `app/Views/admin/popups/list.php` | 관리자 목록 뷰 |
| `app/Views/admin/popups/form.php` | 등록/수정 폼 (TinyMCE + 이미지 + 메뉴 체크박스) |
| `app/Views/themes/default/components/popups.php` | 프론트 팝업 오버레이 렌더링 |
| `public/themes/default/js/popup.js` | 표시/닫기/쿠키 JS |
| `app/Views/themes/default/layouts/main.php` | 팝업 컴포넌트 삽입 |
| `app/Views/themes/spring/layouts/main.php` | spring 테마 팝업 컴포넌트 삽입 |
| `dark.zip / views/layouts/main.php` | dark 테마 팝업 컴포넌트 삽입 |
| `dark.zip / public/css/style.css` | `.site-popup` 다크 스타일 추가 |

### 주요 트러블슈팅
| 항목 | 원인 | 해결 |
|---|---|---|
| spring 팝업 `position:static` | spring CSS에 팝업 스타일 미포함 | `spring/css/style.css`에 `.site-popup` 블록 추가 |

---

## 3. 성능 개선 ✅ 완료

### 배너·팝업 조회 캐싱
- 매 프론트 요청마다 실행되던 배너 1쿼리 + 팝업 2쿼리를 캐시로 제거 (캐시 1시간)
- 활성 데이터 전체를 캐시하고 **노출 기간(started_at/ended_at)·스코프 판정은 PHP에서 처리** → 캐시가 살아있어도 시간 경과에 따른 노출/종료 정확히 동작
- 팝업은 URI별 캐시 키 증가 없이 단일 키(`active_popups`)로 팝업 + 페이지 URL 매핑 저장
- 캐시 무효화: 모델 콜백(`afterInsert/Update/Delete`) + `syncPages()` → 관리자 수정 즉시 반영
- 부수 효과: 날짜 문자열 SQL 직접 보간 패턴 제거

### DB 인덱스 추가 (`php spark migrate` 필요)
| 인덱스 | 용도 |
|---|---|
| `posts (board_id, is_notice, id)` | 게시판 목록·페이징·카운트 — filesort 제거 |
| `posts (deleted_at)` / `post_comments (deleted_at)` | 소프트 삭제 `deleted_at IS NULL` 조건 |
| `banners (position, is_active)` | 캐시 미스 시 활성 배너 조회 |
| `popups (is_active, show_scope)` | 캐시 미스 시 활성 팝업 조회 |
| `inquiries (is_read)` | 관리자 전 페이지 미읽음 문의 카운트 |

### 구현 파일
| 파일 | 설명 |
|---|---|
| `app/Models/BannerModel.php` | `getActiveByPosition()` 캐싱 + 무효화 콜백 |
| `app/Models/PopupModel.php` | `getActiveForPage()` 캐싱 + 무효화 콜백 |
| `app/Database/Migrations/2026-06-10-000001_AddPerformanceIndexes.php` | 인덱스 마이그레이션 |

### 비고
- 기존 캐시(설정 `site_settings`, 메뉴 `nav_menus`)와 합쳐 일반 페이지는 게시판 데이터 외 반복 쿼리 없음
- 게시판 검색은 `LIKE '%키워드%'` 구조라 인덱스 불가 — 수만 건 이상 쌓이면 FULLTEXT 인덱스 전환 검토
