# 아키텍처

## 테마 시스템

`ThemeView`(`app/Libraries/ThemeView.php`)가 CI4 기본 렌더러를 대체합니다. 뷰 탐색 순서:

1. `app/Views/themes/{active_theme}/{view}.php`
2. `app/Views/themes/default/{view}.php`
3. `app/Views/{view}.php` (관리자 뷰, 콘텐츠 뷰 — 테마 적용 대상 아님)

활성 테마는 `settings.active_theme`에 저장됩니다(캐시됨). 새 테마는 `app/Views/themes/{name}/`와 `public/themes/{name}/`에 파일을 두어 추가하며, default와 다른 부분만 재정의하면 됩니다. `Config/Services.php`가 `ThemeView`를 공유 렌더러로 연결합니다.

## BaseController — 전역 데이터 주입

모든 컨트롤러는 `BaseController`를 상속합니다. 매 요청마다 실행되는 `initController()`가 `$this->viewData`에 다음을 주입합니다:

- `$settings` — 사이트 전역 키-값 설정 (캐시됨)
- `$menus` — 내비게이션 트리 (캐시됨)
- `$authUser` — 세션 기반 사용자 정보 (id, nickname, role, loggedIn)
- `$subLeftBanners` — 활성 사이드바 배너 (캐시됨, 관리자 경로에서는 건너뜀)
- `$activePopups` — 현재 URI에 대한 활성 팝업 (캐시됨)
- `$unreadInquiries` — 읽지 않은 문의 수 (admin 역할만)

컨트롤러에서는 `$this->render('view/path', $extraData)`를 사용 — `$viewData`를 자동으로 병합합니다.

## 인증 & 라우팅

- 인증 필터 별칭: `auth` → `App\Filters\AuthFilter`
- 사용법: `['filter' => 'auth:member']` 또는 `['filter' => 'auth:admin']`
- 모든 `/admin/*` 경로는 `auth:admin` 필요
- 동적 페이지 catch-all `(:segment)`는 `Routes.php`에서 반드시 맨 마지막에 위치

## CSRF 예외

다음 경로는 CSRF 토큰 없이 POST를 받으며(에디터 / 미디어 업로드), `Config/Filters.php`에서 제외됩니다:

- `board/image-upload`
- `admin/media/upload`

## 캐싱 전략

CI4 파일 캐시를 다음에 사용합니다:

- `site_settings` — 전체 설정 키-값 맵 (`SettingModel`)
- `nav_menus` — 메뉴 트리 (`MenuModel`)
- `active_banners_{position}` — 위치별 배너 (`BannerModel`)
- `active_popups` — 전체 활성 팝업 + 페이지 URL 매핑 (`PopupModel`)

모델 콜백(`afterInsert/Update/Delete`)이 관리자 쓰기 시 해당 캐시 키를 무효화합니다. 배너/팝업 만료는 캐시된 데이터에 대해 PHP에서 검사하므로 시간 기반 캐시 무효화가 필요 없습니다.

## 소셜 로그인 (OAuth)

`AbstractOAuthProvider` 기반 클래스와 `GoogleProvider`, `NaverProvider`, `KakaoProvider`로 구성됩니다. `OAuthFactory::create(string $provider)`가 프로바이더를 해석합니다. 키는 `Config/OAuth.php`에 있으며 `.env`에서 읽습니다.

## 파일 업로드

| 클래스 | 용도 |
| --- | --- |
| `FileUploader` | 게시글 첨부파일 — 확장자 화이트리스트, 최대 10 MB, 랜덤 hex 파일명 |
| `ImageUploader` | 배너 / 팝업 이미지 — 이미지 전용, 최대 2 MB |
| `MediaUploader` | 관리자 미디어 라이브러리 — 드래그 앤 드롭, `media` 테이블에 경로 저장 |

## DB 스키마 요약

```
users               — 회원 / 관리자 역할, 소셜 로그인 필드
settings            — 키-값 사이트 설정 (active_theme, smtp 등)
menus               — 2단계 내비게이션 트리
pages               — slug 기반 동적 페이지
boards / posts / post_files / post_comments  — 게시판 시스템
inquiries           — 문의 폼 제출
banners / popups / popup_pages               — 마케팅 오버레이
media               — 미디어 라이브러리
```
