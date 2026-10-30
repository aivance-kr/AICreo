# Architecture

## Theme system

`ThemeView` (`app/Libraries/ThemeView.php`) replaces CI4's default renderer. View lookup order:

1. `app/Views/themes/{active_theme}/{view}.php`
2. `app/Views/themes/default/{view}.php`
3. `app/Views/{view}.php` (admin views, content views — not themed)

The active theme is stored in `settings.active_theme` (cached). Add a new theme by placing files under `app/Views/themes/{name}/` and `public/themes/{name}/`, overriding only what differs from default. `Config/Services.php` wires `ThemeView` as the shared renderer.

## BaseController — global data injection

Every controller extends `BaseController`. `initController()`, which runs on every request, injects the following into `$this->viewData`:

- `$settings` — site-wide key-value settings (cached)
- `$menus` — navigation tree (cached)
- `$authUser` — session-based user info (id, nickname, role, loggedIn)
- `$subLeftBanners` — active sidebar banners (cached, skipped on admin paths)
- `$activePopups` — active popups for the current URI (cached)
- `$unreadInquiries` — unread inquiry count (admin role only)

Controllers use `$this->render('view/path', $extraData)` — automatically merges with `$viewData`.

## Auth & routing

- Auth filter alias: `auth` → `App\Filters\AuthFilter`
- Usage: `['filter' => 'auth:member']` or `['filter' => 'auth:admin']`
- All `/admin/*` routes require `auth:admin`
- The dynamic-page catch-all `(:segment)` must always come last in `Routes.php`

## CSRF exceptions

The following paths accept POST without a CSRF token (editor / media upload), excluded in `Config/Filters.php`:
- `board/image-upload`
- `admin/media/upload`

## Caching strategy

Uses CI4 file cache for:
- `site_settings` — full settings key-value map (`SettingModel`)
- `nav_menus` — menu tree (`MenuModel`)
- `active_banners_{position}` — banners per position (`BannerModel`)
- `active_popups` — all active popups + page URL mapping (`PopupModel`)

Model callbacks (`afterInsert/Update/Delete`) invalidate the corresponding cache keys on admin writes. Banner/popup expiry is checked in PHP against the cached data, so no time-based cache invalidation is needed.

## Social login (OAuth)

Consists of an `AbstractOAuthProvider` base class and `GoogleProvider`, `NaverProvider`, `KakaoProvider`. `OAuthFactory::create(string $provider)` resolves the provider. Keys live in `Config/OAuth.php` (read from `.env`).

## File uploads

| Class | Use |
|-------|-------|
| `FileUploader` | Post attachments — extension whitelist, max 10 MB, random hex filename |
| `ImageUploader` | Banner / popup images — images only, max 2 MB |
| `MediaUploader` | Admin media library — drag and drop, stores paths in the `media` table |

### 썸네일

- `App\Libraries\Thumbnail` 이 업로드 시 GD 로 썸네일(긴 변 400px)을 `원본폴더/thumbs/원본파일명` 에 만든다 (jpg·png·webp. gif·svg 제외). 세 업로더가 모두 호출한다.
- 긴 변이 400px 이하이거나 썸네일이 원본보다 작지 않으면 만들지 않는다. 화면에서는 `thumb_url($path)` (`image_helper.php`)가 썸네일이 있으면 썸네일, 없으면 원본 주소를 돌려준다. 목록·미리보기(게시글 첨부, 관리자 미디어·배너·팝업 목록)에 쓰고, 본문·클릭 시 보기는 원본이다.
- 원본 삭제 시 `Thumbnail::remove()` 로 썸네일도 지운다.
- 기존 파일 일괄 생성: `php spark thumbnails:generate [--force]` (`public/uploads` 전체, 이미 있으면 건너뜀).

## DB schema summary

```
users               — member / admin roles, social login fields
settings            — key-value site settings (active_theme, smtp, etc.)
menus               — 2-level navigation tree
pages               — slug-based dynamic pages
boards / posts / post_files / post_comments  — board system
inquiries           — inquiry form submissions
banners / popups / popup_pages               — marketing overlays
media               — media library
series              — 연재 묶음 (slug·title·description·status)
post_series         — 글↔시리즈 다대다 피벗 (PK series_id+post_id, series_order NULL 허용)
```

## 시리즈(연재)

- 한 글은 여러 시리즈에 속할 수 있다(`post_series` 피벗). 외래 키는 없고, 시리즈 삭제 시 `SeriesModel` 이 피벗 행만 지운다(글은 남음).
- 글 상세 상단의 시리즈 상자(`board/_series_nav.php`)는 속한 시리즈마다 하나씩 `SeriesModel::navigationsForPost()` 가 만든다. 캐시 키는 시리즈 단위 `series_nav_{id}` 하나이고 현재 위치·이전·다음은 매번 계산한다. `PostModel` 의 update/delete 훅과 `SeriesModel` 의 소속 변경 메서드가 해당 시리즈 캐시를 지운다.
- 하단의 글 번호 기준 이전/다음 글(`getPrevious`/`getNext`)은 시리즈와 무관하게 그대로 둔다 — 두 내비게이션은 역할이 다르다.
- 시리즈 관리는 `/admin/series`(수정 화면에서 글 검색·번호 일괄 입력으로 글 추가), 글에 지정은 운영자만 글쓰기·수정 화면에서 체크박스+순서(비우면 맨 뒤), 공개 목록은 `/series`, 소개 페이지는 `/series/{slug}`.
- 글쓰기·수정 화면의 "AI 추천" 버튼은 `POST board/series-suggest`(운영자 전용)로 `GroqSeriesService`(`service('groqSeries')`, `GROQ_API_KEY` 공유)에 제목·본문과 `SeriesModel::suggestionCandidates()` 를 보내 어울리는 시리즈를 체크해 준다. 저장은 하지 않으며 후보에 없는 번호는 걸러낸다.

## 글 AI 제안

- 글쓰기·수정 화면의 "AI 제안" 버튼(운영자 전용)은 `POST board/ai-suggest` → `GroqPostAssistService`(`service('groqPostAssist')`, `GROQ_API_KEY` 공유, 타임아웃 5초)로 요약(메타 설명)·태그 후보·제목 후보를 받는다. 저장은 하지 않으며, 화면에서 "적용"을 누른 항목만 입력란에 채우고 폼 저장 때 반영된다.
- 모델 응답은 `strip_tags`·길이(요약 160자, 태그 5개·30자, 제목 3개)·개수 제한을 거쳐 돌려주고, 화면은 `textContent` 로만 출력한다. 실패하면 컨트롤러가 안내 문구를 돌려준다.
- 메타 설명은 `posts.meta_desc`(운영자 입력, 300자). 비어 있으면 글 상세의 메타 설명은 기존처럼 본문 앞부분(`post_excerpt`)을 쓴다.

## IP 차단

- `blocked_ips` 테이블 + `BlockedIpFilter`(전역 before)가 차단 IP의 모든 요청을 403으로 막는다.
- 자동 차단: `php spark ip:auto-block` — 최근 3시간 방문 로그에서 1시간 안에 404(`page_visits.is_not_found=1`)를 3회 이상 낸 IP를 30일간 차단하고, 만료된 차단을 정리한다. **서버 crontab에 1시간마다 등록해야 한다** (예: `0 * * * * cd /path/to/app && php spark ip:auto-block`).
- 즉시 차단: 404 로 스캐너 전형 경로(`*.php`·`.phtml`·`.asp(x)`·`.jsp`·`.cgi`, `wp-admin`·`wp-login`·`xmlrpc`·`.env`·`.git`)를 요청한 IP 는 `NotFoundVisitExceptionHandler::isScannerPath()` 가 그 즉시 30일 차단한다(cron 대기 없음). 방문자 브라우저를 이용한 오차단 방지로 Referer·Sec-Fetch-Site 헤더가 있거나 `admin/*` 인 요청은 즉시 차단하지 않고 일배치에 맡긴다. `wp-content/uploads/*.jpg` 같은 정적 파일은 옛 글의 깨진 링크일 수 있어 제외.
- 수동 차단: 관리자 일별 방문기록(`/admin/stats/day/{date}`)의 IP별 차단 버튼.
- 404 표시는 `NotFoundVisitExceptionHandler`가 한다(404는 일반 after 필터가 돌지 않고, 라우트 미매칭 주소는 before 필터도 돌지 않기 때문).
- Cloudflare 뒤에서 실제 접속자 IP 를 받도록 `Config\App` 생성자가 `proxyIPs` 에 `App\Libraries\CloudflareIps` 대역(`CF-Connecting-IP`)을 등록한다. 엣지 IP 가 아닌 곳에서 온 요청의 헤더는 무시된다. Cloudflare 대역이 바뀌면 `CloudflareIps::RANGES` 를 갱신한다.
- `BlockedIpModel::block()` 은 Cloudflare 엣지 IP 를 차단하지 않고 `false` 를 돌려준다(정상 방문자 오차단 방지).
- `.env` 의 `security.ipWhitelist`(쉼표 구분)에 든 IP 는 `BlockedIpModel::block()` 이 차단하지 않고 `isBlocked()` 도 항상 false 를 돌려준다(기존 차단 행도 무시). IP 는 공개 저장소에 커밋하지 않고 서버 `.env` 에만 둔다.

## 예약 발행

- `posts.publish_at`(TIMESTAMP NULL)이 있으면 예약 글이다. 저장 시 `is_active=0` 이라 공개 목록·RSS·sitemap 쿼리에는 원래 걸러진다. 예약 입력은 운영자 전용(글쓰기·수정 화면의 `publish_at`), 미래 시각만 유효하다(`PostModel::normalizePublishAt()`).
- 이 저장소에는 `Config/Tasks.php` 스케줄러가 없다. `php spark posts:publish-scheduled` 가 시각이 지난 글을 공개하고 `publish_at` 을 비운다. **서버 crontab에 1분마다 등록해야 한다** (예: `* * * * * cd /path/to/app && php spark posts:publish-scheduled`).
- 공개 처리는 `PostModel::update()` 경유라 `seo_sitemap`·`seo_feed`·관련 글·시리즈 캐시 훅이 돈다. 관리자 숨기기/노출 토글은 예약을 풀고 즉시 반영한다.

## 뉴스레터 구독

- 테이블: `subscribers`(이메일·상태 pending/confirmed/unsubscribed·확인/해지 토큰), `newsletter_deliveries`(발송 큐: post_id+subscriber_id 유일, `sent_at` NULL 이면 미발송), `posts.newsletter_enqueued_at`(글당 한 번만 큐에 올리는 표식).
- 신청 `POST /newsletter/subscribe`(푸터·글 하단 폼, 허니팟 `website`, IP당 시간당 5회·이메일당 2회 제한) → 확인 메일 → `GET /newsletter/confirm/{토큰}` 으로 구독 완료. 확인 전 주소에는 발송하지 않는다. 해지는 `/newsletter/unsubscribe/{토큰}`(GET 원클릭, 메일 앱의 `List-Unsubscribe-Post` POST 도 같은 주소). 해지하면 미발송 큐도 지운다. 두 경로는 방문 로그에서 제외한다(주소에 토큰이 있음).
- 큐 적재: `PostModel::enqueueNewsletter` 훅(afterInsert/afterUpdate)이 글이 **공개 + 비밀글 아님 + 공개 게시판 + 쓰기 권한이 admin 인 게시판**일 때 그 시점의 확인된 구독자만 큐에 올린다. 회원·비회원이 쓸 수 있는 게시판 글은 제외(구독자 스팸 통로 방지). 예약 글은 `posts:publish-scheduled` 가 공개할 때, 숨김 글은 노출로 바꿀 때 올라간다. WP 가져오기 글은 가져올 때 표식을 채워 제외한다.
- 발송: `php spark newsletter:send [--limit 50]` 가 큐에서 한 번에 `--limit` 건만 보낸다. 실패한 건은 `sent_at` 이 비어 있어 다음 실행에서 다시 시도한다. 요청 사이클에서는 발송하지 않는다. 메일은 `.env` 의 `email.*`(SMTP)·`fromEmail`(비면 사이트 설정 이메일)을 쓴다.
- 관리자: `/admin/subscribers`(상태·이메일 검색, CSV 내보내기, 삭제).

### 서버 crontab 등록 (필수)

배포 후 서버에서 `crontab -e` 로 아래를 **1분마다** 등록한다. 이게 없으면 큐에 쌓이기만 하고 메일이 나가지 않는다.

```
* * * * * cd /path/to/app && php spark newsletter:send >> /dev/null 2>&1
```

- `/path/to/app` 은 서버의 실제 앱 경로, `php` 는 PHP 8.5 CLI 의 전체 경로(예: `/usr/bin/php8.5`)로 바꾼다. 크론은 PATH 가 짧아 `php` 가 다른 버전을 가리킬 수 있다.
- 웹 서버와 같은 사용자로 등록한다(`writable/` 로그·캐시 권한 문제 방지). 예: `sudo crontab -u www-data -e`.
- 1분 주기에 한 번 50건이므로 시간당 최대 3,000건이다. 구독자가 많으면 `--limit 200` 처럼 올리되 SMTP 업체의 발송 한도를 넘지 않게 한다.
- 이전 실행이 길어져 겹쳐도 `newsletter_deliveries` 의 (글, 구독자) 유일키 덕에 큐는 중복되지 않지만, 같은 건을 두 프로세스가 동시에 보내 중복 메일이 나갈 수 있다. 겹침이 걱정되면 `* * * * * cd /path/to/app && flock -n /tmp/newsletter.lock php spark newsletter:send >> /dev/null 2>&1` 로 등록한다.
- 로그: 발송 실패는 `writable/logs/` 에 `[Newsletter]` 로 남는다(주소는 마스킹). 기능 점검은 수동으로 `php spark newsletter:send` 를 한 번 실행해 "완료: 발송 N건, 실패 M건" 이 나오는지 본다.
- 예약 발행(`posts:publish-scheduled`, 1분)·IP 자동 차단(`ip:auto-block`, 1시간)과 별개의 줄이다.
