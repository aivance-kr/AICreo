# SEO → GEO 적용 전략 (AICreo 보일러플레이트)

> AICreo(1인 웹에이전시용 CI4 보일러플레이트)에 SEO를 정비한 뒤 GEO(Generative Engine Optimization)를
> 올려 **AI 검색(ChatGPT Search·Perplexity·Google AI Overviews·Gemini·Claude 등) 노출도**를 높이는 전략 문서.
>
> 본 문서 범위는 **전략·로드맵·아키텍처 설계**까지다. 실제 코드 구현은 후속 이슈로 분할한다(§9).
>
> **AICura 전략문서와의 근본 차이:** AICura는 JSON API만 존재해 "크롤링 표면 0"에서 SSR 레이어부터 신설해야 했다.
> AICreo는 **이미 SSR HTML 페이지(홈·동적페이지·게시판)와 `SeoHelper`가 존재**한다. 따라서 본 전략은
> "신설"이 아니라 **기존 SEO 기초 보강 → GEO 적층**이며, 무엇보다 이 산출물이 **보일러플레이트 제품 기능**으로
> 내장되어 이 위에 배포되는 모든 클라이언트 사이트가 자동으로 혜택받도록 설계하는 것이 목표다.

---

## 0. 확정 필요 선행 결정사항

| # | 항목 | 권고 결정 | 반영 |
|---|------|-----------|------|
| 1 | 운영 도메인·HTTPS | **환경변수 추상화** — 보일러플레이트는 클라이언트마다 도메인이 다르다. `app.baseURL`(.env)만 주입하면 canonical·OG·sitemap·robots가 자동 반영되도록 전부 `base_url()` 경유 | §3.1, §5 |
| 2 | 공개 페이지 색인 정책 | **색인 허용 전제** — 공개 페이지 기본 `index, follow`. 관리자(`/admin/*`)·인증·비밀글·미리보기는 개별 `noindex` | §5, §8 |
| 3 | AI 크롤러 정책 | **전체 허용** — GPTBot·OAI-SearchBot·PerplexityBot·Google-Extended·ClaudeBot·Bingbot 등 인용·학습 크롤러 모두 허용 (노출 극대화). 단, 클라이언트가 관리자에서 끌 수 있는 스위치 제공 | §6 |
| 4 | 멀티테넌트 SEO 설정 | **admin `settings`로 노출** — 도메인·기본 OG 이미지·조직정보·검증코드를 코드 하드코딩이 아니라 관리자 화면에서 클라이언트가 입력 | §3.2, §8 |

> AICreo는 특정 서비스가 아니라 **재판매되는 템플릿**이다. 따라서 "특정 도메인/특정 업종"을 가정하지 않고
> 모든 결정이 **설정 주입**으로 동작하도록 추상화하는 것이 1순위 제약이다.

---

## 1. 배경 — 현재 AICreo의 크롤링 표면

AICura와 달리 AICreo는 이미 공개 SSR HTML을 서빙한다. 현황을 정확히 진단한다.

### 1.1 이미 존재하는 것 (기반은 있음)

| 영역 | 라우트 | 출력 | 색인 대상? |
|------|--------|------|-----------|
| 홈 | `GET /` (`Front\HomeController`) | HTML | ✅ |
| 동적 페이지 | `GET /(:segment)` (`Front\PageController::show`) — 슬러그 기반 | HTML | ✅ |
| 게시판 목록/상세 | `GET /board/{slug}`, `GET /board/{slug}/{num}` | HTML | ✅ (비밀글 제외) |
| 관리자 | `/admin/*` (`auth:admin`) | HTML | ❌ 내부 운영 |
| 인증 | `/auth/*` | HTML | ❌ noindex 대상 |

- `pages` 테이블은 이미 **`meta_title`·`meta_desc`·`og_image`** 필드를 갖는다 → 페이지 단위 SEO 메타의 소스가 존재.
- `SeoHelper`(`app/Libraries/SeoHelper.php`)가 `<title>`·`description`·OG(title/description/url/site_name/type)·
  `naver-site-verification`·GA 스크립트를 이미 출력한다.
- 테마 레이아웃(`app/Views/themes/default/layouts/main.php`)이 `<head>`에서 `SeoHelper`를 호출 중.
- `settings` 테이블에 **조직정보 자산**이 이미 있다: `site_name`·`site_desc`·`site_logo`·`address`·`phone`·`email`·
  `business_num`·`instagram`·`youtube`·`kakao`·`blog`·`naver_verify`·`ga_id` → **Organization/LocalBusiness JSON-LD의 완벽한 소스**.

### 1.2 없는 것 (SEO 결손 + GEO 전무)

| 항목 | 현재 상태 |
|------|-----------|
| **canonical** | ❌ `SeoHelper`에 없음 (중복 URL·페이지네이션 중복 색인 위험) |
| **robots 메타(페이지별 index/noindex)** | ❌ 없음 — 관리자/인증/비밀글 색인 제어 불가 |
| **Twitter Card / og:image 치수 / og:locale** | ❌ 없음 |
| **JSON-LD 구조화 데이터** | ❌ 전무 (GEO 핵심 신호 부재) |
| **sitemap.xml** | ❌ 없음 (동적/정적 모두) |
| **robots.txt Sitemap 지시자** | ❌ `public/robots.txt`는 `Disallow:` 빈 값만 있고 Sitemap 라인 없음 |
| **llms.txt** | ❌ 없음 (GEO용 사이트 요약) |
| **AI 크롤러 명시 허용** | ⚠️ 와일드카드로만 허용, 의도 미명시 |
| **baseURL** | ⚠️ 클라이언트별 도메인 주입 규약 미정 (`app/Config/App.php`) |

> **결론:** AICreo는 SSR 기반이 있으므로 AICura처럼 웹 레이어를 신설할 필요가 없다.
> 대신 (a) **`SeoHelper`를 canonical·robots·JSON-LD·Twitter까지 확장**하고, (b) **sitemap.xml·robots.txt·llms.txt를
> 동적 라우트로 서빙**하며, (c) 이 모든 것을 **관리자 설정으로 제어 가능한 보일러플레이트 기능**으로 완성하면 된다.

---

## 2. SEO vs GEO — 무엇이 다른가

| 구분 | SEO (전통 검색 최적화) | GEO (생성형 엔진 최적화) |
|------|----------------------|------------------------|
| 목표 | 검색결과 **순위** 상승 → 클릭 유도 | LLM 답변 안에 **인용·언급**되기 |
| 소비 주체 | 사람(클릭) | LLM(요약·인용 후 사람에게 전달) |
| 평가 단위 | 페이지 단위 랭킹 | 문장·사실(fact) 단위 추출 가능성 |
| 핵심 신호 | 키워드, 백링크, Core Web Vitals, 메타 | **명확한 사실 진술**, 구조화 데이터, 인용 가능성, 출처 신뢰도, 엔티티 명료성 |
| 대표 채널 | Google·Naver 검색 | ChatGPT Search, Perplexity, Google AI Overviews, Gemini, Copilot, Claude |

**관계:** GEO는 SEO를 대체하지 않고 **위에 쌓인다.** AI 크롤러도 결국 크롤링 가능한 HTML·구조화 데이터·sitemap에 의존한다.
따라서 **SEO 기반(Phase 1) 정비 후 GEO 레이어(Phase 2)를 적층**한다.

> **보일러플레이트 관점의 GEO 가치:** 소상공인·중소기업 클라이언트 사이트는 백링크·도메인 권위가 약해 전통 SEO 순위 경쟁에서
> 불리하다. 반면 GEO는 **명료한 구조화 데이터와 사실 진술**이 강력한 신호라, 규모가 작아도 AI 검색에서 인용될 여지가 크다.
> 즉 GEO는 AICreo가 고객에게 제공할 **차별화된 세일즈 포인트**가 된다.

---

## 3. 아키텍처 설계 — 기존 자산 확장 + 크롤러 진입점 신설

> 별도 프론트엔드 없이 **현행 CI4 SSR·테마 구조를 그대로 확장**한다. 기존 `SeoHelper`·`SettingModel`·
> `PageModel`·`BoardModel`·`PostModel`을 재사용하므로 추가 인프라가 없다.

### 3.1 신규/변경 구성요소

| 경로 | 용도 | 상태 |
|------|------|------|
| `app/Libraries/SeoHelper.php` | canonical·robots 메타·Twitter Card·og:image 치수·og:locale 추가 | **확장** |
| `app/Libraries/Seo/JsonLdBuilder.php` | schema.org JSON-LD 빌더 (Organization/WebSite/Article/BreadcrumbList 등) | **신규** |
| `app/Controllers/Front/SitemapController.php` | `GET /sitemap.xml` 동적 생성 (정적 파일 아님) | **신규** |
| `app/Controllers/Front/RobotsController.php` | `GET /robots.txt` 동적 생성 (Sitemap 지시자·AI 크롤러 섹션·baseURL 절대경로) | **신규** |
| `app/Controllers/Front/LlmsController.php` | `GET /llms.txt` — 사이트 요약·핵심 링크(GEO용) | **신규** |
| `public/robots.txt` | **삭제/대체** — 정적 파일 제거하고 동적 라우트로 전환(도메인 하드코딩 방지) | **변경** |
| `app/Views/themes/default/layouts/main.php` | `<head>`에 JSON-LD 블록·canonical 주입 | **변경** |

라우트 추가 (`app/Config/Routes.php`) — **동적 페이지 catch-all `(:segment)`보다 반드시 위에 배치**:

```php
// ── 크롤러 진입점 (SEO/GEO) ─── catch-all '(:segment)' 위에 둘 것 ───
$routes->get('sitemap.xml', 'Front\SitemapController::index');
$routes->get('robots.txt',  'Front\RobotsController::index');
$routes->get('llms.txt',    'Front\LlmsController::index');

// ...(중략)...

// 반드시 마지막: 동적 페이지 슬러그 catch-all
$routes->get('(:segment)', 'Front\PageController::show/$1');
```

> ⚠️ **catch-all 충돌 주의:** 현재 `GET /(:segment)`가 슬러그를 잡는다. `sitemap.xml`을 라우트로 서빙하려면
> 반드시 catch-all보다 먼저 선언해야 한다(안 그러면 `PageController::show('sitemap.xml')`로 흘러 404).
> `public/robots.txt` 정적 파일은 웹서버가 먼저 서빙하므로, 동적 robots로 전환하려면 **정적 파일을 삭제**해야 한다.

### 3.2 멀티테넌트 SEO 설정 (보일러플레이트 핵심)

클라이언트마다 도메인·조직정보가 다르므로 **하드코딩 금지, 전부 `settings` 주입**. 관리자 SEO 설정 화면에 다음 키 추가:

| 설정 키 | 용도 | 비고 |
|---------|------|------|
| `og_default_image` | OG 기본 이미지 (페이지에 `og_image` 없을 때 폴백) | 신규 |
| `google_verify` | Google Search Console 인증 메타 | 신규 (`naver_verify`와 대칭) |
| `bing_verify` | Bing Webmaster Tools 인증 (§6.2) | 신규 |
| `org_type` | 조직 스키마 타입 (`Organization` / `LocalBusiness` 등) | 신규 — 업종별 선택 |
| `ai_crawlers_allow` | AI 크롤러 허용 on/off 스위치 | 신규 (§6) |
| (기존) `site_name`·`site_desc`·`site_logo`·`address`·`phone`·`email`·`business_num`·SNS | 조직 JSON-LD 소스 | 재사용 |

기존 `settings`는 `SettingModel`이 `site_settings` 캐시로 관리 → SEO 키도 같은 캐시 경유(추가 인프라 없음).

### 3.3 레이어 책임 (CLAUDE.md 준수)

- **Controller는 얇게:** 캐시 조회 → Model/Service 호출 → 뷰(또는 XML/txt) 렌더. 비즈니스 로직 금지.
- 데이터 접근은 기존 **Model 재사용**(`PageModel::getPublished()`·`BoardModel::getActiveBoards()`·`PostModel`).
  sitemap용 신규 쿼리 중복 작성 금지.
- 출력은 전부 `esc()`. JSON-LD는 `esc()`가 아니라 **`json_encode` 이스케이프 규약**을 별도로 지킨다(§4 주의).

### 3.4 부하 분산·캐시

공개 페이지·크롤러 진입점은 트래픽이 몰릴 수 있어 캐시를 1순위로 설계한다.
CI4 파일 캐시 키는 `:` 등 예약문자를 금지하므로 언더스코어를 쓴다.

| 대상 | 캐시 키 | TTL |
|------|---------|-----|
| sitemap.xml | `seo_sitemap` | 1시간 (페이지/게시판 발행·수정 시 무효화) |
| robots.txt | `seo_robots` | 설정 변경 시 무효화 |
| llms.txt | `seo_llms` | 1시간 |
| 페이지 JSON-LD | 페이지 렌더 캐시에 포함 | 페이지 수정 시 무효화 |

- 기존 모델 콜백 캐시 무효화 패턴(`SettingModel`·`MenuModel`·`BannerModel`의 `afterInsert/Update/Delete`) 재사용.
- `PageModel`·`PostModel`에 `afterInsert/Update/Delete` → `seo_sitemap` 무효화 콜백 추가.

---

## 4. 노출 콘텐츠 → schema.org 매핑

GEO의 핵심은 **구조화 데이터로 사실을 기계 판독 가능하게** 만드는 것이다. AICreo 실제 필드 기준 매핑:

> ⚠️ **JSON-LD 구현 주의:** 인라인 JSON-LD는 (a) `</script>` 조기 종료 방지를 위해 `JSON_HEX_TAG`,
> (b) 한글 이식성·테스트 하네스(비ASCII HTML 엔티티 변환) 우회를 위해 `JSON_UNESCAPED_UNICODE`를 **끄고**
> `\uXXXX` ASCII 인코딩을 쓴다(운영 raw UTF-8도 유효, ASCII가 더 안전). 공유 렌더러(`Config\View`)가
> 직전 요청의 `jsonLd`를 유지하지 않도록 레이아웃에서 `jsonLd` 기본값을 항상 `[]`로 명시한다.

### 4.1 사이트 전역 — `Organization` (또는 `LocalBusiness`) + `WebSite`
소스: `settings` 테이블 / `SettingModel`. **모든 페이지 `<head>`에 1회 출력** (엔티티 지식그래프 신호의 뿌리).

| 필드(소스) | schema.org 속성 |
|------------|----------------|
| `site_name` | `Organization.name` / `WebSite.name` |
| `site_desc` | `description` |
| `site_logo` | `Organization.logo` (절대 URL) |
| `address` | `PostalAddress` (LocalBusiness일 때) |
| `phone` | `telephone` |
| `email` | `email` |
| `business_num` | `identifier` (사업자등록번호) |
| `instagram`·`youtube`·`kakao`·`blog` | `sameAs[]` (SNS 프로필 연결 — 엔티티 신뢰 신호) |
| `base_url()` | `WebSite.url` / `url` |
| `org_type`(설정) | 루트 `@type` — 업종에 맞게 `LocalBusiness` 하위 타입 선택 가능 |

> `sameAs`는 GEO에서 특히 유용하다. LLM이 여러 프로필을 같은 엔티티로 묶어 "이 회사가 맞다"는 확신을 높인다.

### 4.2 동적 페이지 — `WebPage` (+ 필요시 `Article`/`AboutPage`/`ContactPage`)
소스: `pages` 테이블 / `PageModel`

| 필드 | schema.org 속성 |
|------|----------------|
| `meta_title` / `title` | `name` / `headline` |
| `meta_desc` | `description` |
| `content` | `Article.articleBody` (정보성 페이지일 때) |
| `og_image` | `image` (절대 URL, 없으면 `og_default_image` 폴백) |
| `updated_at` | `dateModified` |
| `slug` | canonical URL 구성 (`base_url($slug)`) |

- 페이지 성격에 따라 `layout`/슬러그 규칙으로 `AboutPage`(회사소개)·`ContactPage`(문의)를 구분 출력하면 GEO 인용률↑.

### 4.3 게시판·게시글 — `Article` / `BlogPosting` + `BreadcrumbList`
소스: `boards`·`posts` 테이블 / `BoardModel`·`PostModel`·`PostCommentModel`

| 필드 | schema.org 속성 |
|------|----------------|
| `posts.title` | `Article.headline` |
| `posts.content` | `articleBody` |
| `posts.author_name` / `user_nickname` | `author` (개인정보 — 닉네임/마스킹) |
| `posts.created_at` | `datePublished` |
| `boards.name` → 게시판 | `BreadcrumbList` (홈 › 게시판 › 글) |
| `post_comments` (집계) | `commentCount` / `Comment`(선택) |

> **색인 제어(필수):** `is_secret=1`(비밀글)·`is_notice` 특성·`write_permission`으로 접근 제한된 글은 **`noindex`**.
> 게시판 목록 페이지네이션은 canonical/`rel=next|prev`로 중복 색인 방지.

### 4.4 (선택) FAQ·Q&A 게시판 — `FAQPage`
Q&A/FAQ 성격 게시판은 `FAQPage`/`Question`·`Answer`로 출력하면 AI 답변 인용률이 가장 높다.
게시판 `slug`/설정으로 FAQ 유형을 식별해 스키마를 분기.

---

## 5. 단계별 로드맵

### Phase 1 — SEO 기반 정비 (선행 · 필수)

1. **`SeoHelper` 확장** — canonical, 페이지별 `robots`(index/noindex), Twitter Card,
   `og:image:width/height`, `og:locale=ko_KR`, `google_verify`·`bing_verify` 메타 추가
2. **동적 sitemap.xml** — 발행 페이지(`pages.status=published`)·활성 게시판·공개 글 URL + `lastmod`(`updated_at`)
3. **동적 robots.txt** — `public/robots.txt` 정적 파일 제거 → `RobotsController`로 전환, `Sitemap:` 절대 URL·AI 크롤러 섹션
4. **noindex 규칙 적용** — `/admin/*`·`/auth/*`·비밀글·미리보기·검색결과 페이지
5. **baseURL 환경변수화 규약** — 클라이언트 배포 시 `.env`의 `app.baseURL`만 주입하면 전 링크 절대 URL 자동 반영
6. **Core Web Vitals** — 현재 Bootstrap/아이콘 CDN 사용 중 → 이미지 지연로딩·`og:image` 최적화·모바일 우선 점검
7. **관리자 SEO 설정 화면** — §3.2 신규 키 입력 UI (`SettingController`)
8. **검색엔진 등록 안내** — 배포 가이드에 GSC·Naver 서치어드바이저·Bing 등록 절차 문서화

### Phase 2 — GEO 레이어 (Phase 1 위에 적층)

1. **`JsonLdBuilder` 신설** — §4 매핑(Organization/WebSite/WebPage/Article/BreadcrumbList/FAQPage) 출력, 레이아웃 주입
2. **`llms.txt` 발행** — 사이트 개요·주요 페이지·게시판 목록(발행분)·연락처 요약 (AI 크롤러용 사이트맵 격)
3. **인용 가능한 콘텐츠 구조화** — 핵심 사실(회사소개·서비스·연락처·영업정보)을 **명료한 단문**으로,
   FAQ 섹션(`FAQPage`)으로 "자주 묻는 질문" 형식 제공
4. **엔티티 명료화** — `sameAs`(SNS)·일관된 상호명·주소·사업자번호로 지식그래프 신호 강화
5. **신뢰 신호** — 작성일/갱신일 노출, 사업자 정보(`business_num`) 명시, 저작권·연락처 일관성
6. **콘텐츠 자산화** — 게시판을 활용한 정보성 글(`Article`/`BlogPosting`)을 GEO 인용 자산으로 운영 가이드화

---

## 6. AI 크롤러 접근 정책 (robots.txt)

GEO를 원하면 주요 AI 크롤러를 **명시 허용**해야 한다. 차단 시 인용 대상에서 제외된다.
`RobotsController::index()`가 `settings.ai_crawlers_allow` 스위치에 따라 아래를 동적 생성:

```
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /auth/

# AI 검색/학습 크롤러 (노출 원하면 허용 — 관리자 스위치로 제어)
User-agent: GPTBot            # OpenAI
Allow: /
User-agent: OAI-SearchBot     # ChatGPT Search
Allow: /
User-agent: PerplexityBot
Allow: /
User-agent: Google-Extended   # Gemini / AI Overviews
Allow: /
User-agent: ClaudeBot
Allow: /
User-agent: Bingbot           # Bing (ChatGPT Search 전제, §6.2)
Allow: /

Sitemap: {base_url}/sitemap.xml
```

> ✅ **권고(§0): 전체 허용.** 단, 클라이언트가 학습 크롤러(GPTBot 등)만 차단하고 싶을 수 있으므로
> 관리자 스위치(`ai_crawlers_allow`)로 on/off 가능하게 한다. `Sitemap:`·`Allow` 경로는 전부 `base_url()` 절대경로.

### 6.1 Claude(Brave Search) 대응
Claude의 웹 검색은 자체 크롤러가 아니라 **Brave Search API** 결과를 사용한다. Brave는 전용 크롤러 UA를 광고하지 않으며
**Googlebot이 크롤링 가능한 페이지면 Brave도 크롤링**한다. → `User-agent: * Allow: /`로 이미 충족, 별도 UA 항목 불필요.
Brave가 AI 인용에서 우선하는 신호는 **JSON-LD 구조화 데이터 + 명확한 heading 구조**(§4로 충족).
Brave는 IndexNow 미참여 → 즉시 제출 채널 없음, 자연 재크롤링에 의존. 검증은 대표 질의를 Claude에 직접 넣어 확인(§7).

### 6.2 Bing(ChatGPT Search) 대응
ChatGPT Search는 **Bing 색인에 전적으로 의존**한다(인용 URL은 `OAI-SearchBot`이 Bing으로 먼저 발견). 즉
Bing 미색인 = ChatGPT Search 미노출. → robots에 `Bingbot` 명시, **Bing Webmaster Tools 등록**(`bing_verify` 설정으로
`BingSiteAuth.xml` 서빙 또는 메타 인증), sitemap 제출. (선택) IndexNow로 발행/수정 즉시 통지.

---

## 7. 측정 지표 & 배포 후 체크리스트

| 단계 | 지표 | 도구 |
|------|------|------|
| SEO | 색인 페이지 수, 노출·클릭·평균순위 | Google Search Console, Naver 서치어드바이저 |
| SEO | Core Web Vitals(LCP/INP/CLS) | PageSpeed Insights |
| GEO | AI 검색 인용·언급 빈도 | 대표 질의를 ChatGPT Search·Perplexity·AI Overviews·Claude에 주기 점검 |
| GEO | AI 크롤러 유입 | 서버 로그 User-agent 분석(GPTBot·PerplexityBot·Bingbot 등) |
| 공통 | 구조화 데이터 유효성 | Google Rich Results Test, Schema Markup Validator |

**배포 후 체크리스트 (클라이언트 사이트마다 반복):**
- [ ] `.env`의 `app.baseURL`을 운영 도메인(HTTPS)으로 설정 → canonical·OG·sitemap·robots·llms 절대 URL 자동 반영
- [ ] `/robots.txt`·`/sitemap.xml`·`/llms.txt` 200 응답·내용 확인
- [ ] 관리자 SEO 설정 입력: `site_name`·`site_desc`·`og_default_image`·조직정보·`google_verify`·`naver_verify`·`bing_verify`
- [ ] `/admin`·`/auth`·비밀글 `noindex` 동작 확인
- [ ] Google Search Console 등록 → sitemap 제출
- [ ] Naver 서치어드바이저 등록 → 사이트맵 제출·수집 요청
- [ ] Bing Webmaster Tools 등록(ChatGPT Search 전제) → sitemap 제출
- [ ] Rich Results Test / Schema Validator로 Organization·Article·BreadcrumbList 오류 0 확인
- [ ] 대표 질의를 ChatGPT Search·Perplexity·Claude에 넣어 인용·언급 여부 점검

---

## 8. 리스크 & 보일러플레이트 특수성 ⚠️

- **중복 콘텐츠(템플릿 재사용):** 여러 클라이언트가 같은 보일러플레이트 기본 문구를 그대로 두면 중복 콘텐츠로 평가절하될 수 있다.
  → 배포 가이드에 "기본 페이지 문구는 반드시 교체" 명시, 데모/샘플 콘텐츠는 `noindex` 기본값.
- **도메인 하드코딩 금지:** sitemap·robots·canonical에 특정 도메인이 박히면 재판매 시 오염된다.
  → 전부 `base_url()` 경유(§0, §3.1). 정적 `public/robots.txt`는 삭제하고 동적 라우트로 전환.
- **개인정보/비밀글 색인:** 게시판 비밀글·작성자 정보·문의 내용이 색인되면 사고. → `is_secret`·권한 글 강제 `noindex`, 작성자 마스킹.
- **업종별 스키마 오적용:** 병원·법무 등 규제 업종은 표기·광고 규제가 있으므로, `org_type`을 클라이언트가 선택하되
  규제 업종은 별도 검토 안내. (AICura의 의료광고법 이슈처럼, 업종에 따라 법무 검토가 필요할 수 있음)
- **AI 크롤러 정책 통제권:** 일부 클라이언트는 학습 크롤러 차단을 원할 수 있음 → `ai_crawlers_allow` 스위치로 위임(§6).

---

## 9. 후속 이슈 분할 제안

`feature/* → dev → main` 워크플로우(CLAUDE.md)에 맞춰 다음 단위로 분할한다.

| 순서 | 이슈(제안) | 범위 | 의존 |
|------|-----------|------|------|
| 1 | `SeoHelper` 확장 | canonical·페이지별 robots·Twitter Card·og 치수/locale·google/bing verify | — |
| 2 | 동적 sitemap + robots | `SitemapController`·`RobotsController`, 정적 robots 제거, 라우트를 catch-all 위에 배치, 캐시 무효화 콜백 | 1 |
| 3 | noindex 규칙 | `/admin`·`/auth`·비밀글·미리보기 색인 제어 | 1 |
| 4 | 관리자 SEO 설정 화면 | §3.2 신규 설정 키 + `SettingController` UI | 1 |
| 5 | `JsonLdBuilder` (GEO) | Organization/WebSite/WebPage/Article/BreadcrumbList/FAQPage, 레이아웃 주입 | 1,4 |
| 6 | llms.txt (GEO) | `LlmsController` — 사이트 요약·주요 페이지·게시판·연락처 | 2 |
| 7 | (선택) IndexNow 즉시 색인 | 페이지/글 발행·수정 시 Bing·Naver 즉시 제출 | 2 |
| 8 | 측정·검증 | GSC/Naver/Bing 등록·Rich Results·크롤러 로그 (배포 시점 운영 활동) | 전체 |

---

## 부록 A. 기존 재사용 자산

- `app/Libraries/SeoHelper.php` — 확장 대상(현재 title·desc·OG·naver_verify·GA)
- `app/Models/SettingModel` — `site_settings` 캐시, 조직정보·SEO 설정 소스
- `app/Models/PageModel`(`getPublished`/`getBySlug`) — 페이지 sitemap·메타 소스, 이미 `meta_title/meta_desc/og_image` 보유
- `app/Models/BoardModel`(`getActiveBoards`)·`PostModel`(`getList`) — 게시판·글 sitemap·Article 소스
- `app/Views/themes/default/layouts/main.php` — `<head>` 주입 지점
- 모델 캐시 무효화 패턴(`SettingModel`·`MenuModel`·`BannerModel`의 `afterInsert/Update/Delete`) — sitemap 캐시 무효화에 재사용

## 부록 B. AICura 전략문서 대비 요약

| 항목 | AICura | AICreo |
|------|--------|--------|
| 시작 상태 | JSON API만 (크롤 표면 0) | SSR HTML + `SeoHelper` 존재 (기초 부분 완비) |
| 1순위 작업 | SSR 웹 레이어 **신설** | 기존 `SeoHelper` **확장** + 크롤러 진입점 신설 |
| 제품 성격 | 단일 서비스(성형 광고) | **재판매 보일러플레이트** → 멀티테넌트 설정 추상화가 핵심 |
| 도메인 | 환경변수 추상화 | 동일 — 단 클라이언트마다 주입 반복 |
| 특수 규제 | 의료광고법 (전 페이지) | 업종별 상이 → `org_type`·배포 시 개별 검토 |
| 콘텐츠 자산 | 시술 가이드(Article/FAQ) 신규 | 게시판 활용 정보성 글 + 회사소개/FAQ |
