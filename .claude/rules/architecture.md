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
```

## IP 차단

- `blocked_ips` 테이블 + `BlockedIpFilter`(전역 before)가 차단 IP의 모든 요청을 403으로 막는다.
- 자동 차단: `php spark ip:auto-block` — 최근 2시간 방문 로그에서 1시간 안에 404(`page_visits.is_not_found=1`)를 3회 이상 낸 IP를 30일간 차단하고, 만료된 차단을 정리한다. **서버 crontab에 1시간마다 등록해야 한다** (예: `0 * * * * cd /path/to/app && php spark ip:auto-block`).
- 즉시 차단: 404 로 스캐너 전형 경로(`*.php`·`.phtml`·`.asp(x)`·`.jsp`·`.cgi`, `wp-admin`·`wp-login`·`xmlrpc`·`.env`·`.git`)를 요청한 IP 는 `NotFoundVisitExceptionHandler::isScannerPath()` 가 그 즉시 30일 차단한다(cron 대기 없음). 방문자 브라우저를 이용한 오차단 방지로 Referer·Sec-Fetch-Site 헤더가 있거나 `admin/*` 인 요청은 즉시 차단하지 않고 일배치에 맡긴다. `wp-content/uploads/*.jpg` 같은 정적 파일은 옛 글의 깨진 링크일 수 있어 제외.
- 수동 차단: 관리자 일별 방문기록(`/admin/stats/day/{date}`)의 IP별 차단 버튼.
- 404 표시는 `NotFoundVisitExceptionHandler`가 한다(404는 일반 after 필터가 돌지 않고, 라우트 미매칭 주소는 before 필터도 돌지 않기 때문).
- Cloudflare 뒤에서 실제 접속자 IP 를 받도록 `Config\App` 생성자가 `proxyIPs` 에 `App\Libraries\CloudflareIps` 대역(`CF-Connecting-IP`)을 등록한다. 엣지 IP 가 아닌 곳에서 온 요청의 헤더는 무시된다. Cloudflare 대역이 바뀌면 `CloudflareIps::RANGES` 를 갱신한다.
- `BlockedIpModel::block()` 은 Cloudflare 엣지 IP 를 차단하지 않고 `false` 를 돌려준다(정상 방문자 오차단 방지).
- `.env` 의 `security.ipWhitelist`(쉼표 구분)에 든 IP 는 `BlockedIpModel::block()` 이 차단하지 않고 `isBlocked()` 도 항상 false 를 돌려준다(기존 차단 행도 무시). IP 는 공개 저장소에 커밋하지 않고 서버 `.env` 에만 둔다.
