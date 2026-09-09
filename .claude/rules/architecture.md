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
