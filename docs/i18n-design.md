# 다국어(i18n) 지원 설계안 (이슈 #566)

> 상태: **초안 — 합의 대기**. 합의 전에는 구현하지 않는다. 합의 후 아래 PR 단계로 진행한다.

## 현황

- 단일 언어(ko). `Config\App` 의 `defaultLocale='en'`, `supportedLocales=['en']`, `negotiateLocale=false`. 화면 문구는 뷰에 한글 하드코딩.
- 콘텐츠: `pages`(slug 유일), `posts`(게시판 소속, URL `/board/{slug}/{id}`), `menus`(title·url 직접 입력).
- SEO: `SeoHelper`(canonical = `current_url()`), `SitemapController`(전체 URL 단일 sitemap, 캐시 `seo_sitemap`), `FeedController`·`LlmsController`.
- 라우팅: `/{slug}` catch-all 이 마지막.

## 결정이 필요한 쟁점과 권고

| # | 쟁점 | 권고 | 대안 |
|---|------|------|------|
| 1 | URL 구조 | **경로 접두어** `/en/...` (기본 언어 ko 는 접두어 없음 → 기존 URL·SEO 보존) | 서브도메인(CF/DNS 부담), 쿼리 `?lang=`(색인 불리) |
| 2 | 번역 저장 | **글/페이지 단위 번역 행** — 번역본도 독립된 `posts`/`pages` 행, `translation_group_id` 로 묶음 | 필드별 번역 테이블(편집 UX 복잡, 쿼리 증가) |
| 3 | 메뉴 | `menus.locale` 컬럼 — 언어별 메뉴 트리를 따로 관리 | 제목만 번역 테이블 |
| 4 | UI 고정 문구 | CI4 `app/Language/{locale}/` 로 점진 이전. 1차는 공개 화면 핵심 문구만 | 전부 한 번에(범위 과다) |
| 5 | 번역 없는 페이지 | 해당 언어에 번역본이 없으면 hreflang 에서 제외, 언어 전환 UI 는 그 언어 홈으로 | 기본 언어 폴백 노출(중복 콘텐츠) |
| 6 | 지원 언어 | 설정 `locales`(예 `ko,en`) + `default_locale`. 코드 상수 아님 | |

## 데이터 모델

```
posts.locale               VARCHAR(5) NOT NULL DEFAULT 'ko'
posts.translation_group_id INT UNSIGNED NULL    -- 같은 글의 번역본끼리 공유(원본 id 사용)
pages.locale / pages.translation_group_id        -- 동일
pages.slug 유일키 → (locale, slug) 복합 유일키
menus.locale               VARCHAR(5) NOT NULL DEFAULT 'ko'
```

- 기존 행은 모두 `ko`, group NULL → 무변경 호환. 마이그레이션은 가산적이라 롤백 안전.
- 시리즈·태그·게시판은 1차 범위 밖(언어 중립 취급). 게시판 이름 번역은 후속.
- 번역본 조회: `SELECT … WHERE translation_group_id = ?` (인덱스 `idx_posts_translation_group_id`).

## 라우팅·로케일 결정

- `LocaleFilter`(전역 before): 첫 경로 세그먼트가 `supportedLocales` 에 있으면 로케일 설정 후 접두어를 떼고 나머지 라우팅. 없으면 기본 언어.
- 라우트는 `/(en)/…` 를 중복 정의하지 않고 `Config\Routing` 의 접두어 그룹 또는 필터 내부 URI 재작성으로 처리(구현 PR 에서 CI4 방식 확정).
- 자동 언어 리다이렉트(Accept-Language)는 **하지 않는다** — 크롤러·캐시 혼선 방지. 전환은 수동 UI.
- 관리자(`/admin/*`)는 언어 접두어 대상 아님(항상 ko).

## SEO

- `SeoHelper`: canonical 은 해당 언어 URL, `<link rel="alternate" hreflang="xx" href>` 를 번역본마다 + `x-default`(기본 언어) 출력. 번역이 없으면 그 언어는 생략(자기 참조 hreflang 필수 유지).
- sitemap: 언어별 `sitemap-{locale}.xml` + `sitemap.xml` 은 sitemap index. 각 `<url>` 에 `xhtml:link` 로 alternate 명시. 캐시 키 `seo_sitemap_{locale}`, 쓰기 훅에서 전 언어 키 삭제.
- RSS·llms.txt·OG 는 1차에서는 기본 언어만. 언어별 피드(`/en/feed`)는 2차.
- `og:locale`, `<html lang>` 을 현재 언어로.

## 관리자 흐름

- 글/페이지 수정 화면에 "번역" 박스: 지원 언어별 상태(없음/있음) 표시 → "번역 추가" 는 같은 `translation_group_id` 로 새 행을 만들고 원본 제목·본문을 복사해 시작 → 이후 일반 편집 화면.
- 목록에 언어 배지·언어 필터. 번역본 삭제는 개별(그룹 유지), 원본 삭제 시 번역본은 남고 group 만 승계(최소 id).
- 메뉴 관리: 언어 탭으로 전환.
- 설정: 지원 언어·기본 언어·언어 전환 UI 표시 여부.
- 관리자 활동 로그에 번역 생성 기록. 민감값 없음.

## 언어 전환 UI

- 헤더 우측 `ko | en`. 현재 페이지에 번역본이 있으면 그 URL, 없으면 해당 언어 홈. 모든 테마에서 쓰는 공용 컴포넌트 하나(`components/lang_switcher`).

## PR 단계 (각 단계 독립 머지·롤백 가능)

1. **기반**: 설정(`locales`, `default_locale`), `LocaleFilter`, `<html lang>`, 마이그레이션(컬럼만), `locale_url()` 헬퍼, 테스트. 화면 동작 무변화.
2. **콘텐츠 번역 연결**: 모델 조회에 locale 반영(`getBySlug`, 글 목록·상세), 번역본 조회 API(`translationsOf`), 단위·기능 테스트.
3. **SEO**: hreflang, canonical, 언어별 sitemap/index.
4. **관리자**: 번역 박스·배지·필터, 메뉴 언어 탭, 설정 화면.
5. **전환 UI + UI 고정 문구**: 언어 전환기, `Language/en` 핵심 문구.
6. (후속) 피드·llms·OG·게시판명 번역.

## 리스크·주의

- `/{slug}` catch-all 과 언어 접두어 충돌: 접두어는 지원 로케일 코드로만 매칭, 페이지 slug 로 로케일 코드(`en` 등) 금지 검증 추가.
- 캐시 키(`site_settings`, `nav_menus`, `active_banners_*`, `active_popups`)는 언어 의존 데이터가 생기면 키에 locale 포함 필요 — 메뉴는 2단계에서 `nav_menus_{locale}` 로.
- 방문 통계(`page_visits`)·검색·태그·아카이브는 1차에서 언어 구분 없음 → 단계 2에서 검색·목록에 locale 조건 포함 여부 확정.
- 뉴스레터·예약 발행 훅은 번역본도 같은 규칙으로 동작(번역 글을 구독자에게 보낼지는 후속 결정).

## 합의 요청

1. URL 접두어 방식(쟁점 1)과 번역 행 방식(쟁점 2) 확정
2. 1차 지원 언어 (ko 기본 + en 만?)
3. PR 단계 순서·범위 확정
