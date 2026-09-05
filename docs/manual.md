# AICreo 운영·개발 매뉴얼

> 1인 웹에이전시를 위한 CodeIgniter 4 기업 홈페이지 템플릿 — 동적 페이지·게시판 CMS·문의폼·마케팅 오버레이·관리자 패널.
> 코어는 재사용하고 테마만 교체해 빠르게 납품하는 구조로 설계되었습니다.

이 문서는 저장소 문서(README·SETUP·CLAUDE.md)와 실제 구현(`app/Controllers`·`app/Views`·`Routes.php`)을 리뷰해 작성한 통합 매뉴얼입니다.

**대상**

| 구분 | 내용 |
|------|------|
| 운영자 · 사이트 관리자 | 납품받은 사이트를 직접 운영. `/admin` 패널에서 콘텐츠·회원·설정을 다룸 → [2. 관리자 패널](#2-로그인--대시보드) |
| 개발자 · 납품 담당 | 설치·배포·테마 커스터마이징·구조 이해 → [1. 설치](#1-설치--최초-설정) · [12. 아키텍처](#12-개발자-아키텍처) |

---

## 목차

1. [설치 & 최초 설정](#1-설치--최초-설정)
2. [로그인 & 대시보드](#2-로그인--대시보드)
3. [콘텐츠 관리](#3-콘텐츠-관리) — 페이지 · 게시판 · 전체 게시물 · 미디어
4. [배너 & 팝업](#4-배너--팝업)
5. [회원 & 문의](#5-회원--문의)
6. [사이트 · 메뉴 · 설정](#6-사이트--메뉴--설정)
7. [테마 & 소셜 로그인](#7-테마--소셜-로그인)
8. [회원가입 · 로그인 · 프로필](#8-회원가입--로그인--프로필)
9. [게시판 사용법](#9-게시판-사용법)
10. [문의 & 동적 페이지](#10-문의--동적-페이지)
11. [SEO / GEO 엔드포인트](#11-seo--geo-엔드포인트)
12. [개발자 아키텍처](#12-개발자-아키텍처)
13. [납품 워크플로우](#13-납품-워크플로우) — SEO/GEO 배포 검증 체크리스트

---

## 1. 설치 & 최초 설정

저장소 루트가 하나의 CI4 프로젝트입니다. 모든 `php spark`·`composer` 명령은 루트에서 실행합니다.

### 요구 사항

- **PHP 8.5+** — `composer.json`의 require/platform에 고정
- **MySQL / MariaDB** — 운영 및 테스트용 DB
- **Composer** (TinyMCE는 API 키가 필요 없는 공개 CDN을 사용)

### 설치 절차

1. **환경 파일 생성** — `cp env .env` 후 DB 접속 정보와 `CI_ENVIRONMENT` 편집
2. **타임존 설정** — `app/Config/App.php`의 `appTimezone = 'Asia/Seoul'`
3. **마이그레이션** — `php spark migrate` (테이블 생성 + 기본 데이터: 게시판 3개 · 관리자 계정)
4. **업로드 권한** (Linux) — `chmod -R 755 public/uploads writable`
5. **개발 서버** — `php spark serve` → http://localhost:8080

> **기본 관리자 계정**: `admin@example.com` / `admin1234!`
> 최초 로그인 후 반드시 비밀번호를 변경하세요.

### 품질 게이트 (커밋 전 필수)

| 명령 | 역할 |
|------|------|
| `composer cs` | PHP-CS-Fixer 스타일 점검(dry-run) · `composer cs:fix`로 자동 정규화 |
| `composer analyse` | PHPStan 정적 분석 (레벨 6) |
| `composer test` | PHPUnit — 운영과 분리된 MySQL 테스트 DB 필요 |
| `composer ci` | cs + analyse + test 한 번에 |

> **테스트 DB 분리**: 테스트는 매 실행 시 데이터가 초기화됩니다. `db:create ..._test`로 운영과 다른 DB를 만들고 `.env`의 `database.tests.*`에 별도 자격증명을 등록하세요.

---

## 2. 로그인 & 대시보드

관리자 패널은 `/admin`으로 진입하며 모든 하위 경로는 `auth:admin` 필터로 보호됩니다. 관리자 역할 계정만 접근할 수 있습니다.

### 관리자 메뉴 구성

| 그룹 | 항목 |
|------|------|
| 콘텐츠 | 대시보드 · 페이지 관리 · 게시판 관리 · 전체 게시물 · 미디어 |
| 마케팅 | 배너 관리 · 팝업 관리 |
| 회원 · 문의 | 회원 관리 · 문의 수신함 |
| 사이트 | 메뉴 관리 · 사이트 설정 · 테마 관리 · 소셜 로그인 |

### 대시보드 — `GET /admin`

로그인 직후 첫 화면. 통계 카드를 누르면 해당 관리 화면으로 바로 이동합니다.

- **통계 카드 4종** — 총 게시글 · 총 회원 · 전체 문의 · **미읽음 문의**(클릭 시 미읽음 필터로 이동)
- **최근 활동** — 최근 문의 5건(미읽음 배지) · 최근 게시글 5건(게시판·작성자·날짜)

---

## 3. 콘텐츠 관리

### 페이지 관리 — `/admin/pages`

슬러그 기반 동적 페이지를 CRUD 합니다. 발행하면 검색엔진에 색인을 즉시 제출(IndexNow)합니다.

| 필드 | 설명 / 옵션 |
|------|-------------|
| 슬러그 *(필수)* | URL 경로. 영문·`-`·`_`만. 신규 작성 시에만 입력 (예: about, service) |
| 레이아웃 | `default` 기본 · `contact` 문의폼 포함 · `landing` 랜딩 |
| 제목 *(필수)* | 페이지 제목 |
| 내용 | TinyMCE 에디터 (이미지 업로드 지원) |
| 메타 타이틀 / 설명 | SEO 검색 결과용 메타 정보 |
| 순서 | 목록 정렬 숫자 |
| 상태 | `published` 공개 · `draft` 초안 |

> 홈페이지(`/`)를 관리자에서 편집하려면 페이지를 추가하고 슬러그를 `home`으로 입력합니다. 상태가 `공개`이면 홈페이지의 기본 샘플 섹션 대신 해당 페이지가 표시됩니다. 내용이 비어 있으면 제목만 표시되므로 TinyMCE에서 본문을 작성한 뒤 저장합니다. 페이지를 초안으로 바꾸거나 삭제하면 기존 기본 홈이 다시 표시됩니다. 상단·하단 배너와 최신 공지는 기존 관리 기능을 통해 별도로 관리합니다.

### 게시판 관리 — `/admin/boards`

독립된 게시판을 생성하고 읽기/쓰기 권한과 첨부 정책을 설정합니다.

| 필드 | 설명 / 옵션 |
|------|-------------|
| 슬러그 *(필수)* | 영문·`-`·`_`. 신규 시만 입력, 수정 시 읽기전용 (예: notice, free, qna) |
| 게시판 이름 *(필수)* | 표시 이름 |
| 읽기 권한 | `guest` 비회원 · `member` 회원 · `admin` 관리자 |
| 쓰기 권한 | `guest` · `member`(기본) · `admin` |
| 페이지당 글 수 | 5~100 (기본 15) |
| 파일 / 이미지 첨부 | 각각 체크박스로 허용 여부 지정 |
| 활성화 | 수정 시 게시판 노출 on/off |

목록의 **게시글 관리**에서 특정 게시판의 글을 조회하고 강제 삭제(첨부 포함)할 수 있습니다.

### 전체 게시물 — `/admin/posts`

- 전 게시판 글을 **게시판 필터 + 키워드 검색**으로 통합 조회 (기본 20건씩)
- 공지·비밀 배지 표시, **강제 삭제** 시 첨부 파일까지 함께 제거

### 미디어 라이브러리 — `/admin/media`

- 드래그·파일 선택으로 업로드 → 즉시 그리드 반영 (24개씩 페이지네이션)
- 이미지별 **alt 텍스트** 인라인 수정, **경로 복사** 버튼, 삭제

> **활용 팁**: 로고·파비콘·배너 이미지는 여기서 업로드한 뒤 복사한 경로를 각 설정 화면의 이미지 필드에 붙여넣는 흐름이 표준입니다.

---

## 4. 배너 & 팝업

시작일·종료일 기반으로 자동 노출되는 마케팅 오버레이입니다. 기간을 비워두면 제한 없이 상시 노출됩니다.

### 배너 관리 — `/admin/banners`

| 필드 | 설명 / 옵션 |
|------|-------------|
| 배너 이미지 *(필수)* | jpg·jpeg·png·gif / 최대 2MB (신규 등록 시) |
| 위치 | `main_top` 메인상단 · `main_bottom` 메인하단 · `sub_left` 서브좌측 · `sub_right` 서브우측 |
| 링크 URL / 열기 | 클릭 시 이동 주소 + `_self` 현재창 / `_blank` 새창 |
| 우선순위 | 낮을수록 먼저 표시 |
| 시작일 / 종료일 | 노출 기간(datetime). 종료일 비우면 제한 없음 |
| 운영 중 | 활성/비활성 체크박스 |

이미지를 교체하면 기존 파일은 자동 삭제됩니다. 목록은 위치 → 우선순위 순으로 정렬됩니다.

### 팝업 관리 — `/admin/popups`

| 필드 | 설명 / 옵션 |
|------|-------------|
| 제목 *(필수)* | 최대 200자 |
| 이미지 / 본문 | 이미지(≤2MB)와 TinyMCE 텍스트를 함께 또는 단독으로 사용 |
| 노출 범위 | `all` 전체 · `home_only` 홈 전용 · `specific` 특정 페이지 |
| 노출 페이지 | 범위가 `specific`일 때 메뉴 목록에서 다중 선택 |
| X / Y 좌표 | 화면 표시 위치 px (기본 20 / 20) |
| 우선순위 · 기간 · 운영중 | 배너와 동일 |

---

## 5. 회원 & 문의

### 회원 관리 — `/admin/users`

- **검색·필터** — 닉네임·이메일·아이디 키워드, 역할(`admin`/`user`), 상태(활성/비활성). 기본 20명씩
- **수정** — 닉네임 · 역할 변경 · 활성 상태 토글
- **삭제** — 회원 제거

> **본인 계정 보호**: 로그인 중인 관리자 본인 계정은 역할·상태 변경 및 삭제가 차단됩니다.

### 문의 수신함 — `/admin/inquiries`

- 전체 / **미읽음** 필터 (기본 20건). 미읽음에는 **NEW** 배지
- 상세 열람 시 자동으로 **읽음** 처리 — 발신자·이메일·연락처·내용 확인
- 문의 삭제 가능. 방문자가 폼을 제출하면 관리자 이메일로도 자동 전송됩니다

---

## 6. 사이트 · 메뉴 · 설정

### 메뉴 관리 — `/admin/menus`

상단 GNB 내비게이션을 편집합니다. 2단계(상위·하위) 드롭다운 구조를 지원합니다.

| 필드 | 설명 |
|------|------|
| 제목 *(필수)* | 메뉴 표시 이름 |
| URL *(필수)* | 이동 경로 (예: /about) |
| 상위 메뉴 | 부모 메뉴 지정 시 하위 항목(드롭다운)이 됨 |
| 순서 / 타겟 | 정렬 숫자 · `_self` 같은창 / `_blank` 새창 |
| 활성화 | 노출 on/off (수정 시) |

목록에서 **수정**을 누르면 상단 폼이 자동으로 채워지고, 저장·삭제 후 메뉴 캐시가 자동 초기화됩니다.

### 사이트 설정 — `/admin/settings/{그룹}`

키-값 설정을 탭으로 나눠 관리합니다. 각 필드는 타입에 따라 텍스트·체크박스·드롭다운·이미지 입력으로 자동 렌더링됩니다.

| 탭 | 관리 항목 |
|----|-----------|
| `general` 기본 | 사이트명·설명·로고·파비콘, 조직 유형(`Organization`·`LocalBusiness`·`Corporation`·`ProfessionalService`·`Store`) 등 |
| `contact` 연락처 | 전화·이메일·주소·영업시간 |
| `sns` SNS | 소셜 채널 링크 |
| `seo` SEO | 메타·Google Analytics·네이버 인증·AI 크롤러 허용 등 |
| `footer` 푸터 | 푸터 문구·사업자 정보 |

> **이미지 필드 사용법**: 이미지형 설정은 미디어 라이브러리에서 경로를 복사해 입력란에 붙여넣으면 미리보기가 표시됩니다.

---

## 7. 테마 & 소셜 로그인

### 테마 관리 — `/admin/settings/theme`

ZIP 파일 업로드 한 번으로 테마를 설치하고, 카드에서 클릭 한 번으로 전환합니다. 콘텐츠 뷰(게시판·인증·관리자)는 테마 영향을 받지 않습니다.

1. **ZIP 업로드** — 파일명이 테마 이름이 됩니다 (my-theme.zip → my-theme). 필수 파일: `views/layouts/main.php`, `public/css/style.css`
2. **자동 검사** — Zip-slip 방지, 확장자 화이트리스트(views→.php / public→css·js·이미지·폰트), `default` 예약어 보호
3. **적용** — 설치된 테마 카드에서 **이 테마 적용** 클릭 시 즉시 전환. `thumbnail.png`가 있으면 카드 미리보기 표시

```
my-theme.zip
├── views/                    → app/Views/themes/my-theme/
│   ├── layouts/main.php      ★ 필수
│   └── components/           navbar · footer · contact_form
└── public/                   → public/themes/my-theme/
    ├── css/style.css         ★ 필수
    ├── js/main.js
    └── thumbnail.png         관리자 미리보기(선택)
```

저장소에 `dark.zip`·`violet.zip`·`spring.zip` 샘플 테마가 포함되어 바로 업로드해 테스트할 수 있습니다.

### 소셜 로그인 — `/admin/settings/oauth`

Google · Naver · Kakao 로그인을 지원합니다. 보안상 키는 UI 입력이 아닌 `.env` 파일에 직접 등록합니다. 이 화면은 콜백 URL 안내와 각 플랫폼 바로가기를 제공합니다.

| 제공자 | 콜백(Redirect) URL |
|--------|--------------------|
| 구글 | `{base_url}/auth/social/google/callback` |
| 네이버 | `{base_url}/auth/social/naver/callback` |
| 카카오 | `{base_url}/auth/social/kakao/callback` |

```
# .env 예시
oauth.google.client_id     = YOUR_ID.apps.googleusercontent.com
oauth.google.client_secret = YOUR_SECRET
oauth.naver.client_id      = YOUR_NAVER_CLIENT_ID
oauth.kakao.client_id      = YOUR_KAKAO_REST_API_KEY
```

---

## 8. 회원가입 · 로그인 · 프로필

방문자·회원 관점의 인증 기능입니다. 비밀번호는 bcrypt로 해시 저장되며 응답·로그에서 제외됩니다.

### 회원가입 — `/auth/register`

| 필드 | 검증 규칙 |
|------|-----------|
| 이메일 | 이메일 형식 + **중복 불가** (`is_unique`) |
| 닉네임 | 2~20자 + **중복 불가** |
| 비밀번호 | 8자 이상 |
| 비밀번호 확인 | 위 비밀번호와 일치 |

### 로그인 & 프로필 — `/auth/login` · `/auth/profile`

- **로그인** — 이메일 + 비밀번호 또는 소셜 로그인. 권한 없이 글쓰기를 시도해 로그인한 경우 원래 페이지로 자동 복귀(5분 보존)
- **프로필** — 기본 정보 탭(닉네임 수정) · 비밀번호 변경 탭. **소셜 로그인 계정은 비밀번호 변경 불가**

### 소셜 로그인 흐름

1. `/auth/social/{provider}` — CSRF 방지용 state 발급 후 제공자 인증 페이지로 이동
2. 콜백에서 프로필 조회 → 기존 소셜ID는 갱신, 같은 이메일은 **연동**, 신규는 **자동 가입**(닉네임 중복 시 숫자 접미사)

---

## 9. 게시판 사용법

게시판별 권한 설정에 따라 **비회원 / 회원 / 관리자**가 할 수 있는 일이 달라집니다.

### 목록 & 검색 — `/board/{slug}`

- 공지글은 최상단 고정(배지 표시), 비밀글은 🔒 아이콘
- 검색 타입: 제목 / 내용 / 제목+내용. 검색 결과는 SEO 색인 제외(noindex)

### 글쓰기 — `/board/{slug}/write`

| 항목 | 내용 |
|------|------|
| 제목 · 내용 | 제목 최대 255자, 내용은 TinyMCE 위지윅(이미지 붙여넣기·업로드) |
| 비회원 작성 | 이름 + 비밀번호(4자↑) 입력 → 이후 수정·삭제 인증에 사용 |
| 비밀글 | 작성자·관리자만 열람 |
| 공지글 | **관리자만** 지정 가능(상단 고정, 최대 5개 표시) |
| 파일 첨부 | 이미지(jpg·png·gif·webp) / 문서(pdf·doc·xls·ppt·zip·hwp 등) · 최대 10MB · 복수 선택 |

> **비회원 수정·삭제 흐름**: 수정/삭제 시 비밀번호 재확인 모달 → 검증 통과 시 일회성 세션 토큰 발급 → 수정 폼 진입. 저장된 비밀번호는 해시로 비교됩니다.

### 보기 · 댓글 — `/board/{slug}/{id}`

- 본문·첨부 이미지 그리드·파일 다운로드(다운로드 횟수 카운트)
- 댓글: 회원(닉네임) / 비회원(이름+비밀번호). 삭제는 작성자·관리자만(소프트 삭제)

---

## 10. 문의 & 동적 페이지

### 문의폼 — `POST /inquiry/submit`

`contact` 레이아웃 페이지에 포함됩니다. 제출 시 DB 저장 + 관리자 이메일 자동 발송.

| 필드 | 규칙 |
|------|------|
| 이름 *(필수)* | 최대 100자 |
| 이메일 *(필수)* | 이메일 형식 |
| 연락처 | 선택 |
| 제목 | 선택 |
| 내용 *(필수)* | 최소 10자 |

### 동적 페이지 — `/{slug}`

관리자가 만든 페이지를 `layout` 값에 따라 기본·문의·랜딩 뷰로 렌더링합니다. 발행된 페이지는 sitemap에 포함되고 JSON-LD(WebPage)가 자동 생성됩니다.

---

## 11. SEO / GEO 엔드포인트

검색엔진과 AI 크롤러를 위한 자동 생성 엔드포인트입니다. 각 응답은 1시간 캐시됩니다.

| 경로 | 역할 |
|------|------|
| `/sitemap.xml` | 홈·발행 페이지·공개 게시판·공개 게시글 구조 제공(비밀글·비활성 제외) |
| `/robots.txt` | 크롤러 정책. `/admin`·`/auth` 차단, AI 크롤러 허용 여부는 SEO 설정으로 제어 |
| `/llms.txt` | AI 모델용 사이트 요약(GEO 표준) — 주요 페이지·게시판·연락처를 Markdown으로 |
| `/indexnow-key.txt` | Bing/IndexNow 소유권 검증 키. 미설정 시 404. 공개 글 발행 시 URL 자동 제출 |

---

## 12. 개발자 아키텍처

### 테마 시스템

`ThemeView`가 CI4 기본 렌더러를 대체하며 다음 순서로 뷰를 탐색합니다:

1. `app/Views/themes/{active_theme}/{view}.php`
2. `app/Views/themes/default/{view}.php` (폴백)
3. `app/Views/{view}.php` (관리자·콘텐츠 뷰 — 테마 미적용)

활성 테마는 `settings.active_theme`에 저장되고 캐시됩니다. 새 테마는 default와 다른 파일만 재정의하면 됩니다.

### 전역 데이터 주입

모든 컨트롤러는 `BaseController`를 상속하고, 매 요청 `initController()`가 `$viewData`에 `$settings`·`$menus`·`$authUser`·`$subLeftBanners`·`$activePopups`·`$unreadInquiries`를 주입합니다. 컨트롤러에서는 `$this->render('view', $extra)`로 병합 렌더링합니다.

### 라우팅 · 인증 · CSRF

- 필터: `auth:member` / `auth:admin`. 모든 `/admin/*`는 `auth:admin`
- 동적 페이지 catch-all `(:segment)`는 `Routes.php` 최하단에 위치해야 함
- CSRF 예외(에디터·미디어 업로드): `board/image-upload`, `admin/media/upload`

### 캐싱 전략

| 캐시 키 | 대상 |
|---------|------|
| `site_settings` | 전체 설정 맵 (SettingModel) |
| `nav_menus` | 메뉴 트리 (MenuModel) |
| `active_banners_{position}` | 위치별 배너 (BannerModel) |
| `active_popups` | 활성 팝업 + 페이지 매핑 (PopupModel) |

모델 콜백(`afterInsert/Update/Delete`)이 관리자 쓰기 시 캐시를 무효화합니다. 배너·팝업 만료는 PHP에서 검사하므로 시간 기반 무효화가 불필요합니다.

### 파일 업로드 라이브러리

| 클래스 | 용도 |
|--------|------|
| `FileUploader` | 게시글 첨부 — 확장자 화이트리스트, 최대 10MB, 랜덤 hex 파일명 |
| `ImageUploader` | 배너·팝업 이미지 — 이미지 전용, 최대 2MB |
| `MediaUploader` | 미디어 라이브러리 — 드래그앤드롭, `media` 테이블 경로 저장 |

### DB 스키마

```
users              회원·관리자 역할, 소셜 로그인 필드
settings           키-값 사이트 설정 (active_theme, smtp …)
menus              2단계 내비게이션 트리
pages              slug 기반 동적 페이지
boards / posts / post_files / post_comments   게시판 시스템
inquiries          문의 폼 제출
banners / popups / popup_pages                마케팅 오버레이
media              미디어 라이브러리
```

### 보안 요약

- 업로드: 확장자 화이트리스트(php·exe 차단), 저장 파일명 `bin2hex(random_bytes(16))`
- 비밀번호: `password_hash()` / `password_verify()`, CSRF 기본 필터
- 게시글 본문 XSS 정제: `<script>`·`on*` 핸들러·`javascript:`·`<iframe>` 등 제거
- Query Builder 전용(raw SQL 문자열 연결 금지), 뷰는 `esc()` 필수

---

## 13. 납품 워크플로우

코어는 재사용하고 테마·설정만 교체해 클라이언트별 사이트를 빠르게 출고하는 표준 절차입니다.

1. **복제 & DB** — 저장소 clone → `.env` DB 설정 → `php spark migrate` *(~10분)*
2. **기본 설정** — `/admin` 로그인 → 회사명·연락처·SNS·SEO 입력 *(~10분)*
3. **콘텐츠** — 메뉴 편집 → 페이지 작성 → 게시판 권한 설정 *(1~2시간)*
4. **테마** — `public/themes/{client}/` + 필요 시 `app/Views/themes/{client}/` 오버라이드 → ZIP 업로드 또는 직접 배치 → **테마 전환** *(30분~)*
5. **배포** — 서버 업로드, 업로드 권한 설정, `CI_ENVIRONMENT=production`

> **클라이언트 교체 시 변경 지점**: 회사명·연락처(`settings/general`·`contact`) · SNS(`settings/sns`) · GA/네이버(`settings/seo`) · 메뉴(`menus`) · 페이지(`pages`) · 게시판 권한(`boards`) · 로고/파비콘(미디어) · 디자인(테마).

### SEO/GEO 배포 검증 체크리스트

5단계(배포) 직후 **클라이언트 사이트마다 반복**하는 운영 체크리스트입니다. 코드 작업이 아니라 운영 활동이며, 전략 배경은 [`docs/seo-geo-strategy.md`](seo-geo-strategy.md) §7을 참조하세요.

- [ ] `.env`의 `app.baseURL`을 운영 도메인(HTTPS)으로 설정 — canonical·OG·sitemap·robots·llms의 절대 URL이 자동으로 이 값을 따름
- [ ] `/robots.txt`·`/sitemap.xml`·`/llms.txt` 200 응답·내용 확인 (§11 참조)
- [ ] `/admin/settings/seo`에서 SEO 탭 입력: 사이트명·설명·`og_default_image`·조직정보(`org_type`)·`google_verify`·`naver_verify`·`bing_verify`
- [ ] `/admin`·`/auth`·게시판 비밀글이 `noindex`로 응답하는지 확인
- [ ] Google Search Console 등록 → sitemap 제출
- [ ] 네이버 서치어드바이저 등록 → 사이트맵 제출·수집 요청
- [ ] Bing Webmaster Tools 등록(ChatGPT Search 노출 전제) → sitemap 제출
- [ ] Google Rich Results Test / Schema Markup Validator로 `Organization`·`Article`·`BreadcrumbList` 오류 0 확인
- [ ] 대표 질의를 ChatGPT Search·Perplexity·Claude에 넣어 인용·언급 여부 점검

---

*AICreo · CodeIgniter 4 웹에이전시 보일러플레이트 매뉴얼 — 저장소 문서와 구현된 화면 리뷰 기반. MIT License.*
