# 플러그인 아키텍처 구현 문서

> 기준 구현: [aivance-kr/AILign](https://github.com/aivance-kr/AILign) — AICreo 를 복사해 그 위에 플러그인 인프라를 올린 저장소.
> 이 문서는 AILign 의 구현을 **AICreo 본체에 이식**하거나, AICreo 파생 프로젝트에 같은 구조를 올릴 때의 설계·절차·주의점을 정리한다.
> AILign 쪽 원본 설계: `docs/superpowers/specs/2026-08-15-plugin-infrastructure-design.md`, `2026-08-15-entitlement-design.md`, `2026-08-26-plugin-dependency-entitlement-design.md`, `docs/plugin-events.md`.

## 1. 목적과 범위

AICreo 코어는 건드리지 않고 기능을 **독립 모듈(플러그인)** 단위로 추가·제거·판매할 수 있게 한다.

| 단계 | 내용 | AICreo 이식 필요도 |
|---|---|---|
| 1. 인프라 | 스캔·등록·활성화·제거, 부팅 로딩, ZIP 설치, 관리자 화면 | **필수** (이 문서의 핵심) |
| 2. Entitlement | 사용자별 보유 권한, 만료=읽기 전용, `entitlement` 필터 | 유료/회원별 제공이 필요할 때만 |
| 3. 과금 | 결제·구독·크레딧 | AILign 고유. 이식 대상 아님 |

플러그인이 "관리자가 켜고 끄는 기능 모듈" 정도면 1단계만 이식한다. 회원에게 팔 거라면 2단계까지.

## 2. 핵심 설계 결정

### 2.1 `Plugins\{Name}` 네임스페이스 (`App\Plugins` 아님)

CI4 마이그레이션 러너는 네임스페이스 단위로 동작한다. `App\` 하위에 두면 코어 마이그레이션과 한 덩어리가 되어 플러그인별 실행·롤백이 안 된다. 별도 네임스페이스여야 `php spark migrate -n "Plugins\Quote"` 가 성립한다.

- 디렉터리: `app/Plugins/{PascalCase}/`
- 네임스페이스: `Plugins\{PascalCase}` → 경로 `app/Plugins/{PascalCase}`
- 디렉터리명은 `plugin.json` 의 `key`(snake_case)를 PascalCase 로 변환한 값이어야 한다 (`ai_quote` → `AiQuote`). 어긋나면 매니페스트 검증 실패.

### 2.2 부팅 시점엔 DB 가 없다 → 활성 목록 캐시 파일

`Config\Autoload` 는 오토로더 생성 **이전**에 인스턴스화된다. DB 도 서비스도 없다. 그런데 어떤 플러그인이 활성인지 알아야 PSR-4 를 등록할 수 있다.

- 운영 상태의 **진실**: DB `plugins` 테이블
- 부팅용 **투영**: `writable/plugins/active.php` (`return ['quote' => 'Quote', ...];`)
- 부팅 경로는 이 파일만 읽는다. 파일이 없거나 깨졌거나 배열이 아니면 **빈 배열 = 전부 비활성** (부팅 실패보다 안전한 실패).
- 쓸 때는 임시 파일 → `rename` 으로 **원자적 교체**, 이후 `opcache_invalidate`.
- 로더(`active_plugins.php`)는 클래스가 아니라 **클로저를 반환하는 평범한 파일**이다. 오토로더 이전에 `require` 해야 하기 때문. 런타임 클래스(`ActivePluginCache`)와 테스트가 같은 파일을 쓰도록 로직을 한 벌로 유지한다.
- 디렉터리명은 `^[A-Z][A-Za-z0-9]*$` 만 허용 — 캐시 변조 시 경로 탈출(`../`) 방지. 디렉터리와 `plugin.json` 이 실제로 있는지도 등록 전에 확인한다.

### 2.3 플러그인 라우트는 루트 `Routes.php`, catch-all 직전 수집

CI4 모듈 auto-discovery 는 `Config/Routes.php` 를 코어 라우트 **이후**에 수집한다. AICreo 코어 마지막 줄이 `(:segment)` catch-all 이라 자동 수집된 라우트는 전부 동적 페이지 404 로 떨어진다. 그래서:

- 파일명은 `Config/Routes.php` 가 **아니라** 플러그인 루트의 `Routes.php` (auto-discovery 대상에서 빠짐)
- 코어 `Routes.php` 가 catch-all **직전에** `service('pluginManager')->loadRoutes($routes)` 로 명시 수집
- `Events.php` 도 같은 방식으로 `app/Config/Events.php` 에서 `loadEvents()`

### 2.4 플러그인 간 직접 의존 금지 — Events 로만 통신

`Pricing` 이 `Quote` 클래스를 `use` 하는 순간 둘은 따로 팔 수 없는 하나가 된다.

- 이벤트명: `{plugin_key}.{과거형 동사}` (`quote.accepted`). 명령형·진행형 금지 — 요청이 아니라 사실의 통지.
- 페이로드: 배열 하나, snake_case 키, 식별자 + 판단에 필요한 값만. 발행자는 키 삭제·의미 변경 금지(추가만).
- 구독은 플러그인 루트 `Events.php` 에서만. 활성일 때만 로드되므로 비활성 플러그인의 구독은 존재하지 않는다.
- 구독자는 놓쳐도 되는 쪽이어야 한다. 반드시 실행돼야 하는 후속은 발행자 책임 또는 큐.
- `requires.plugins` 는 "없으면 동작하지 않음" 선언이다. 이벤트를 듣기만 하는 관계는 넣지 않는다.

### 2.5 비활성화 ≠ 데이터 삭제

| 동작 | 라우트·이벤트 | 마이그레이션 | 테이블 데이터 |
|---|---|---|---|
| deactivate | 내림 | 유지 | **유지** |
| uninstall | 내림 | **rollback** | **삭제** (유일한 파괴 경로) |

## 3. 디렉터리·매니페스트 규약

```
app/Plugins/{PluginName}/
├── plugin.json            # 필수
├── Routes.php             # 선택, 플러그인 루트
├── Events.php             # 선택, 플러그인 루트
├── Config/Services.php    # 선택, 플러그인 전용 서비스
├── Controllers/  Models/  Services/  Libraries/  Exceptions/  Domain/
├── Views/
└── Database/Migrations/
public/plugins/{plugin_key}/   # CSS/JS/이미지 (ZIP 의 public/ 이 여기로 이동)
```

```json
{
  "key": "cnc_engine",
  "name": "CNC 견적엔진",
  "version": "1.0.0",
  "requires": { "core": ">=1.0", "plugins": ["quote", "pricing"] },
  "license_type": "subscription",
  "menu": [
    { "title": "가공 시간 계수", "route": "my/cncengine/coefficients", "icon": "bi-cpu" }
  ]
}
```

| 필드 | 필수 | 규칙 |
|---|---|---|
| `key` | ✅ | `^[a-z][a-z0-9_]*$`, 32자 이내, 전역 유일 |
| `name` | ✅ | 1~64자 |
| `version` | ✅ | `MAJOR.MINOR.PATCH` |
| `license_type` | ✅ | `onetime` \| `subscription` \| `credit` (1단계만 이식하면 의미 없는 값이라도 스키마는 유지) |
| `requires.core` | ❌ | 제약 문자열. **현재 AILign 도 문법만 검증, 해석 안 함** |
| `requires.plugins` | ❌ | key 배열. 강제는 2단계 `PluginDependencyService` |
| `menu[]` | ❌ | `{title, route, icon}`. `route` 는 선행 `/` 금지 |
| `widgets[]` | ❌ | 위젯 슬롯 등록. 아래 «위젯 슬롯» 참고 |

주의: 매니페스트의 `license_type` 은 **신규 등록 시 기본값 제안**일 뿐이다. 재스캔(`upsertFromManifest`)은 `name`·`version`·`manifest` 만 갱신하고, 운영자가 정한 `status`·가격·`license_type` 은 덮어쓰지 않는다.

## 위젯 슬롯 (#627)

플러그인이 공개 화면의 원하는 자리에 작은 단위(위젯)로 끼어든다. 슬롯은 **코어 뷰가 `<?= widget_slot('이름') ?>` 를 부르는 자리**이고, 이름은 `^[a-z][a-z0-9_]*$` 자유 문자열이다 — 새 자리가 필요하면 그 뷰에 한 줄 넣으면 된다.

```json
"widgets": [
  { "slot": "sidebar", "view": "Views/widgets/recent", "order": 10,
    "class": "Plugins\\Sample\\Widgets\\RecentNotesWidget", "cache": 300, "entitled": false }
]
```

| 필드 | 필수 | 규칙 |
|---|---|---|
| `slot` | ✅ | 슬롯 이름 |
| `view` | ✅ | 플러그인 디렉터리 기준 `Views/...` (상위 경로·확장자 금지) |
| `order` | ❌ | 정수, 기본 0. 작을수록 먼저, 같으면 plugin key 순 |
| `class` | ❌ | `Plugins\{Dir}\` 네임스페이스의 `WidgetInterface` 구현. `data()` 가 뷰 변수를 만든다 (위젯은 컨트롤러 없이 렌더되므로 데이터 조회는 여기서) |
| `cache` | ❌ | 렌더 결과 캐시 초, 기본 300, `0` 이면 캐시 안 함. 키 `{plugin_key}_widget_{해시}`(슬롯·뷰·로케일) |
| `entitled` | ❌ | `true` 면 해당 플러그인 Entitlement 가 없는(`None`) 사용자에게 숨김. 권한 검사는 캐시 조회보다 먼저 한다 |

- `PluginManager::widgets($slot)` 는 **활성 플러그인**의 위젯만 모은다 → 비활성화·제거하면 다음 요청부터 사라진다(캐시된 HTML 은 조회 자체가 안 됨).
- `WidgetRenderer` 는 위젯 단위 try/catch — 예외는 로그(`위젯 렌더 실패`)만 남기고 그 위젯만 비운다.
- 기본 제공 슬롯: `sidebar`(`blog_sidebar.php` 끝, 블로그형 테마 공용), `footer`(`site_footer_info.php` 끝). 다른 자리(헤더·본문 위/아래·상세·목록)는 해당 뷰에서 `widget_slot()` 을 부르면 된다.
- 뷰는 `esc()` 필수. 다국어는 위젯 뷰가 `lang()`/`$request->getLocale()` 로 처리하고, 캐시 키에 로케일이 들어간다.
- 사용자별로 내용이 달라지는 위젯은 `cache: 0` 으로 둔다(캐시는 사용자 구분이 없다).
- 예: `app/Plugins/Sample` 의 `RecentNotesWidget` + `Views/widgets/recent.php`.

## 4. 구성 요소 (AILign 기준 파일 → AICreo 이식 위치)

모두 `app/Libraries/Plugin/` 아래.

| 파일 | 책임 |
|---|---|
| `PluginManifest` (`final readonly`) | `plugin.json` 파싱·**생성 시점 전체 검증**. 인스턴스가 존재 = 유효. `fromArray`/`fromJsonFile`/`toArray`/`toPascalCase` |
| `MenuItem` | 메뉴 항목 값 객체 (title 필수, route 상대경로) |
| `LicenseType` (enum) | Onetime / Subscription / Credit + `label()` |
| `ActivePluginCache` | `active.php` 읽기/원자적 쓰기, `psr4Map()` |
| `active_plugins.php` | 오토로더 이전용 순수 클로저 (§2.2) |
| `PluginManager` | `scan`, `sync`, `activate`, `deactivate`, `uninstall`, `loadRoutes`, `loadEvents`, `writeActiveCache`. 경로를 생성자 주입 → 테스트에서 임시 디렉터리로 교체 |
| `PluginInstaller` | ZIP 설치 (§6) |
| `app/Models/PluginModel` | `plugins` 테이블. `findByKey`, `upsertFromManifest`, `markMissing`, `activeKeys` |
| `app/Exceptions/PluginException`, `InvalidManifestException` | 도메인 예외 |
| `app/Controllers/Admin/PluginController` | `/admin/plugins` 목록·동기화·활성/비활성·제거·ZIP 업로드·가격 수정 |

`plugins` 테이블: `plugin_key`(UNIQUE `uniq_plugins_plugin_key`), `name`, `version`, `status`(`inactive`/`active`/`missing`), `license_type`, `price_*`, `manifest`(JSON), `installed_at`, 타임스탬프. 컬럼명이 `key` 가 아니라 `plugin_key` 인 이유는 `KEY` 가 MySQL 예약어이기 때문. 모든 시각 컬럼은 `TIMESTAMP` (전역 타임존 규약).

### 4.1 상태 전이

```
(파일 추가) --sync--> inactive --activate--> active --deactivate--> inactive
                         ^                     |
                         |                     +--(파일 사라짐, sync)--> missing
                         +------(파일 복구, sync)------------------------+
uninstall: 어느 상태에서든 rollback → 레코드 삭제 → 디렉터리 삭제
```

- `missing` 은 레코드를 지우지 않는다 (가격·이력 보존, 복구 시 부활). `missing` 은 활성화 불가.
- 상태가 바뀔 때마다 `writeActiveCache()` 로 캐시 재투영.

### 4.2 마이그레이션 실행/롤백 — 함정

- **활성화**: 플러그인 네임스페이스를 런타임 오토로더에 등록(`registerNamespace`)한 뒤 `service('migrations')->setNamespace($ns)->latest()`. 활성화 요청 시점엔 부팅 캐시에 아직 없으므로 런타임 등록이 필요하다. **이미 등록돼 있으면 다시 등록하지 않는다** — 중복 등록되면 같은 마이그레이션이 두 번 조회된다.
- **제거(롤백)**: `MigrationRunner::regress()` 를 **쓰면 안 된다.** 내부에서 namespace 를 null 로 되돌려 전체 배치를 훑으므로 플러그인 하나 제거가 **코어 테이블까지 전부 삭제**한다. 대신 `findNamespaceMigrations($ns)` 로 목록을 만들고, `uid` 로 중복 제거 후 `krsort`(나중 버전부터) 하여 `force($path, $ns)` 로 하나씩 `down()`.
- `force()` 는 토글이다 — 같은 파일을 두 번 부르면 되돌린 것이 다시 실행된다. 중복 제거가 필수인 이유.
- `composer.json` 의 `exclude-from-classmap: **/Database/Migrations/**` 는 이미 AICreo 에 있다.

## 5. 코어 수정 지점 (AICreo 기준)

AILign 은 코어 수정을 아래로 한정했다. AICreo 이식도 동일하게 최소화한다.

| 파일 | 수정 | 이유 |
|---|---|---|
| `app/Config/Autoload.php` | **생성자 추가**: `active_plugins.php` 를 `require` 하여 `$this->psr4` 에 병합 | 오토로더 생성 이전의 유일한 훅 |
| `app/Config/Routes.php` | catch-all 직전 `service('pluginManager')->loadRoutes($routes);` | 라우트 우선순위 |
| `app/Config/Events.php` | `service('pluginManager')->loadEvents();` | 이벤트 구독 수집 |
| `app/Config/Services.php` | `pluginManager()` 서비스 (테스트 환경은 `active_test.php` 캐시 사용) | 단일 진입점, 테스트가 dev 캐시를 덮어쓰지 않게 |
| `app/Config/Filters.php` | (2단계) `entitlement` 별칭 | 접근 관문 |
| 관리자 사이드바 | `/admin/plugins` 메뉴 | 진입점 |
| `.gitignore` | `writable/plugins/`, `writable/plugins_tmp/` | 생성 산출물 |
| `phpstan.neon` | 플러그인 경로 포함, `Views/*` 제외 | 정적 분석 |

### 5.1 AICreo 에서 따로 확인할 것 (AILign 에는 없던 AICreo 고유 요소)

AICreo 는 AILign 이 복사해 간 시점 이후 기능이 늘었다. 이식 시 충돌 가능 지점:

1. **catch-all 이 두 개다.** 현재 `Routes.php` 끝에는 i18n 언어별 `{locale}/(:segment)` 루프와 `(:segment)` 가 연달아 있다. `loadRoutes()` 는 **둘 모두보다 위**에 넣는다.
2. **다국어 접두어.** 공개 라우트는 `$front` 클로저로 언어별 그룹에 재등록된다. 플러그인 라우트는 기본적으로 **접두어 없는 단일 경로**로만 등록한다. 플러그인 공개 화면이 다국어를 지원해야 하면 별도 설계(i18n 2단계와 함께) 필요. 플러그인 key·URL 첫 세그먼트가 언어 코드(`ko`, `en`)와 겹치지 않게 한다.
3. **ThemeView.** AICreo 는 `ThemeView` 가 뷰 경로를 `themes/{active}` → `themes/default` → `app/Views` 순으로 해석한다. 플러그인 뷰를 AILign 처럼 **네임스페이스 절대 경로**(`\Plugins\Quote\Views\index`)로 렌더하면 이 탐색을 거치지 않는다 — 즉 **테마 영향 없이 고정 레이아웃**이 된다. 테마별 오버라이드를 지원할지(예: `themes/{t}/plugins/{key}/...` 우선 조회) 결정 필요. 지원하지 않으면 플러그인 뷰는 관리자 셸/자체 레이아웃만 쓴다고 명시.
4. **BaseController 전역 주입.** `$settings`, `$menus`, `$authUser` 등은 `render()` 로 자동 병합되므로 플러그인 컨트롤러도 `BaseController` 를 상속하면 그대로 받는다. 관리자 경로 판정(`$subLeftBanners` 생략 등)이 URI 기반이라 `/admin/...` 밖 플러그인 URL 에서는 일반 페이지로 취급된다.
5. **VisitLogFilter / BlockedIpFilter / RedirectFilter (전역 before).** 플러그인 경로도 방문 로그에 쌓인다. 토큰이 주소에 들어가는 플러그인 경로(공유 링크 등)는 `VisitLogFilter` 제외 목록에 추가해야 한다 (`preview/*`, `newsletter/*` 선례).
6. **CSRF.** 플러그인 POST 는 전역 CSRF 를 그대로 받는다. 에디터/업로드류가 토큰 없이 POST 해야 하면 `Config/Filters.php` 제외 목록 수정이 코어 변경이 된다 — 가급적 토큰을 실어 보내도록 플러그인 쪽을 맞춘다.
7. **업로더/캐시.** AICreo 의 `FileUploader`·`ImageUploader`·`MediaUploader` 는 플러그인에서 재사용 가능. 캐시 키는 `{plugin_key}_...` 접두어로 충돌 방지.
8. 신규 시각 컬럼은 반드시 `TIMESTAMP` (타임존 규약).

## 6. ZIP 설치기 (`PluginInstaller`)

플러그인은 **실행 코드**를 담으므로 테마 설치기보다 검증을 강화한다. 원칙: **검증을 전부 통과하기 전에는 목적지에 아무것도 쓰지 않는다.**

1. 모든 엔트리를 설치 **전에** 검사
   - 개수 ≤ 2000, 압축 해제 총량 ≤ 50MB
   - Zip-slip 차단: `..`, 선행 `/`, `\`, `C:` 드라이브 접두어 거부
   - 확장자 화이트리스트: `php json css js map md png jpg jpeg gif svg webp ico woff woff2 ttf` (그 외 거부)
   - 최상위에 `plugin.json` 필수
2. 임시 디렉터리(`writable/plugins_tmp/plugin_*`)에 해제
3. `plugin.json` 검증. **설치 디렉터리명은 ZIP 안의 폴더 구조가 아니라 `key` 에서 유도**한다 (ZIP 을 신뢰하지 않음)
4. 같은 key 가 있으면 교체, `rename` 으로 `app/Plugins/{Name}` 에 이동
5. ZIP 내 `public/` 은 `public/plugins/{key}/` 로 이동
6. `finally` 에서 임시 디렉터리 정리
7. 설치 직후 상태는 **inactive**. 업로드가 곧 활성화는 아니다. 컨트롤러는 설치 후 `sync()` 만 호출.

> ⚠️ ZIP 설치는 서버에 임의 PHP 를 올리는 기능이다. `/admin/plugins/upload` 는 `auth:admin` 필수이며, 운영에서는 신뢰하는 관리자만 쓰도록 하고 업로드 사용 여부는 서버 `.env` 의 `plugins.allowUpload`(기본 `false`, `Config\Plugins`)로 제어한다. 관리자 화면(DB 설정)이 아니라 `.env` 인 이유는 관리자 계정이 탈취돼도 웹에서 스스로 켜서 코드를 올릴 수 없게 하기 위해서다. 꺼져 있으면 `/admin/plugins/upload` 는 거절하고 화면에는 업로드 폼이 보이지 않는다.

## 7. 라우트·접근 제어 (플러그인 작성 규약)

```php
// app/Plugins/CncEngine/Routes.php
$routes->group('my/cncengine', ['filter' => 'entitlement:cnc_engine'], static function ($routes): void {
    $routes->get('actuals', '\Plugins\CncEngine\Controllers\ActualRecordController::index');
});
```

- 컨트롤러는 **선행 `\` 를 붙인 전체 네임스페이스**로 지정한다.
- 1단계만 이식하면 필터는 기존 `auth:admin` / `auth:member` 를 쓴다. 2단계 이식 시 `entitlement:{key}` 하나로 통일 — 이 필터가 **로그인 검사까지 포함**하므로 `auth:member` 를 겹쳐 걸지 않는다 (관문이 둘이면 하나를 빠뜨릴 수 있다).
- 컨트롤러 안에서 권한 체크를 자체 구현하지 않는다.

## 8. Entitlement (2단계 — 선택 이식)

`user_plugins` 테이블(`user_id`, `plugin_key`, `type`, `status`, `purchased_at`, `expires_at`, `cancelled_at`; `UNIQUE(user_id, plugin_key)`)과 아래 클래스.

| 클래스 | 역할 |
|---|---|
| `Entitlement` (`final readonly`) | 접근 판정 `accessLevel()` 이 존재하는 **유일한 장소** |
| `AccessLevel` (enum) | `Full` / `ReadOnly` / `None`, `canWrite()` |
| `EntitlementStatus` (enum) | `active` / `cancelled` / `revoked` |
| `UserPluginModel` | `findEntitlement`, `entitlementsFor`, `grant`, `revoke`, `cancel`, `holdersOf` |
| `EntitlementFilter` | `None` → `/my/plugins` 리다이렉트, `ReadOnly` + 쓰기 메서드 → 차단 |
| `PluginDependencyService` | `requires.plugins` 중 사용자가 미보유한 선행 플러그인 목록 |
| `My\PluginController` | 사용자용 목록·구독 해지·크레딧형 무료 활성화 (필터 **밖**에 둠 — 안에 두면 무한 리다이렉트) |

핵심 규칙:

- **만료를 DB 에 저장하지 않는다.** `status` 에 `expired` 가 없다. `expires_at` 으로 매번 계산 → 갱신 크론 지연에 접근 제어가 의존하지 않는다. 경계는 포함(만료 "시각까지" 사용 가능).
- **만료 구독 = 읽기 전용.** 데이터 삭제·잠금 금지, 조회 허용, `POST/PUT/PATCH/DELETE` 만 차단. 그래서 **플러그인 GET 핸들러는 DB 를 쓰지 않는다** (규약).
- 만료일 없는 구독은 데이터 결함 → `ReadOnly` (결함일 때 열어주지 않음).
- 해지는 `status`·`cancelled_at` 만 바꾸고 `expires_at` 은 건드리지 않는다 → 읽기 전용 상태에서도 해지 가능.
- 회수(`revoked`) 사용자가 "무료 활성화" 액션으로 스스로 되살리지 못하게 별도 차단 (AILign 최종 리뷰에서 발견된 구멍).

### 8.1 `UserScopedModel` 트레이트

플러그인의 사용자 데이터 테이블은 전부 `user_id` 컬럼 + 이 트레이트. `beforeFind/Insert/Update/Delete` 콜백으로 `user_id` 를 **자동 주입·필터**한다.

- 스코프를 결정할 수 없으면(세션 없음 등) 조용히 넘어가지 않고 `UserScopeMissingException`.
- CLI·워커·테스트: `->forUser($id)`. 정당한 전역 조회(관리자): `->withoutUserScope(fn ($m) => ...)` — 우회를 한 곳으로 모아 추적 가능하게.
- 이 트레이트를 쓰는 모델은 `before*` 콜백 프로퍼티를 **직접 선언하지 않는다** (트레이트가 점유).
- `user_id` 필터 누락은 버그가 아니라 **보안 결함**으로 취급, 테스트로 격리를 검증한다.

## 9. 플러그인 작성 체크리스트

1. `app/Plugins/{Name}/plugin.json` 작성 (디렉터리명 = key 의 PascalCase)
2. `Config/Services.php` 가 필요하면 `BaseService` 상속 (외부 클라이언트는 인터페이스 + 서비스 등록 → 테스트에서 fake 교체)
3. 마이그레이션은 `Database/Migrations/` 에. **테이블 접두어로 충돌 방지**(`pricing_materials` 처럼), 시각 컬럼 `TIMESTAMP`, `down()` 필수 (제거 시 실제로 실행됨)
4. `Routes.php`: 전체 네임스페이스 + 필터. GET 은 읽기 전용
5. 다른 플러그인 클래스 `use` 금지. 필요하면 `Events::trigger('{key}.{과거형}', [...])`
6. 뷰는 `$this->render('\Plugins\Name\Views\x', ...)`; 표시 문자열 `esc()`
7. 정적 자산은 `public/plugins/{key}/` (ZIP 에선 플러그인 안 `public/`)
8. 관리자 화면에서 재스캔 → 활성화 → 라우트 접근 → 비활성화 → (테스트 데이터로) 제거 순으로 확인

## 10. 테스트 전략

AILign 이 둔 검증 범위 — 이식 시 동일하게 확보한다.

- 매니페스트: 필드별 정상/실패 케이스, 디렉터리명 불일치
- `ActivePluginCache`/로더: 파일 없음·깨짐·비배열 → 빈 배열, 경로 탈출 이름 거부, 존재하지 않는 디렉터리 건너뜀, 원자적 쓰기
- `PluginManager`: 임시 디렉터리 주입, `scan`(깨진 매니페스트는 건너뛰고 나머지 정상), `sync`(신규 inactive / 사라짐 missing / 복구 inactive), 활성화 마이그레이션 실행, 제거 시 **코어 테이블이 남아 있는지**
- `PluginInstaller`: Zip-slip, 허용되지 않는 확장자, 엔트리 수/용량 초과, `plugin.json` 누락, 설치 실패 시 목적지 무변경
- 라우트 수집: 활성만 수집, catch-all 보다 먼저 매칭
- `UserScopedModel`/Entitlement: 타 사용자 데이터 접근 불가, 만료 경계, 읽기 전용에서 쓰기 차단
- 테스트 환경: `pluginManager` 서비스가 `writable/plugins/active_test.php` 를 쓰게 해 dev 캐시를 덮어쓰지 않는다. paratest 병렬이면 `WRITEPATH` 가 워커별로 갈리므로 캐시 경로를 하드코딩하지 않고 항상 `WRITEPATH` 경유.

## 11. 운영 주의

- **`writable/plugins/active.php` 는 git 에 없다.** 새 서버/클린 배포 직후엔 전부 비활성이다. 배포 스크립트(`deploy.yml`: `git reset --hard` → `composer install` → `migrate --all` → `cache:clear`)에 재스캔·활성 캐시 재생성 단계를 넣거나, 관리자가 `/admin/plugins` 에서 동기화해야 한다.
- 활성 캐시는 서버별 상태다. 다중 서버면 파일 캐시가 서버마다 따로이므로 공유 스토리지 또는 서버별 동기화가 필요하다.
- ZIP 업로드는 `writable/plugins_tmp/` 와 `app/Plugins/` 쓰기 권한이 필요하다. 읽기 전용 배포 이미지에서는 업로드 설치가 안 된다.
- `php spark migrate --all` 은 활성/비활성 구분 없이 모든 네임스페이스를 돌린다 — 비활성 플러그인 테이블도 만들어질 수 있다. 의도라면 무방하나, 활성화 시에만 만들고 싶으면 배포 스크립트의 `--all` 을 정리해야 한다.

## 12. 이식 순서 제안 (AICreo)

각 단계는 별도 `feature/*` PR, `composer ci` 통과.

1. **기반**: `Exceptions`, `LicenseType`, `MenuItem`, `PluginManifest` + 테스트
2. **부팅 로더**: `active_plugins.php`, `ActivePluginCache`, `Config\Autoload` 생성자 수정 + 테스트
3. **레지스트리**: `plugins` 마이그레이션, `PluginModel`, `PluginManager`(+서비스 등록) + 테스트
4. **연결**: `Routes.php`(catch-all·locale catch-all 위), `Events.php`, `.gitignore`, `phpstan.neon`
5. **설치기**: `PluginInstaller` + 보안 테스트
6. **관리자 화면**: `Admin\PluginController`, 뷰, 사이드바 메뉴
7. **샘플 플러그인** (`Sample`): 스캔→활성화→마이그레이션→라우트→렌더→제거 전 경로를 통합 테스트로 고정
8. *(선택)* Entitlement 일괄: `user_plugins`, `UserScopedModel`, 필터, `/my/plugins`

## 13. 결정이 필요한 항목

| 항목 | 선택지 | 비고 |
|---|---|---|
| 이식 범위 | 1단계만 / 2단계(entitlement)까지 | 회원별 판매 계획이 없으면 1단계 |
| 코드 공유 방식 | AICreo 에 복사 이식 / 별도 Composer 패키지로 분리 | 패키지는 `active_plugins.php`(오토로더 이전 실행) 때문에 `Autoload` 훅 설계가 까다로움. AILign 은 복사 방식 |
| 플러그인 뷰와 테마 | 고정 레이아웃 / 테마별 오버라이드 | §5.1-3 |
| 플러그인 URL 다국어 | 미지원 / 지원 | §5.1-2 |
| ZIP 업로드 | 운영 허용 / 설정으로 차단 | **결정: `.env` `plugins.allowUpload`, 기본 차단** (§6) |
| 비활성 플러그인 마이그레이션 | `--all` 허용 / 활성 시에만 | §11 |

## 부록. AILign 참조 파일 맵

| 주제 | AILign 경로 |
|---|---|
| 로더 | `app/Libraries/Plugin/active_plugins.php`, `ActivePluginCache.php`, `app/Config/Autoload.php` |
| 매니저/설치기 | `app/Libraries/Plugin/PluginManager.php`, `PluginInstaller.php` |
| 매니페스트 | `app/Libraries/Plugin/PluginManifest.php`, `MenuItem.php`, `LicenseType.php` |
| 권한 | `Entitlement.php`, `AccessLevel.php`, `EntitlementStatus.php`, `PluginDependencyService.php`, `app/Filters/EntitlementFilter.php`, `app/Models/UserPluginModel.php` |
| 사용자 스코프 | `app/Models/Traits/UserScopedModel.php` |
| 관리자/회원 화면 | `app/Controllers/Admin/PluginController.php`, `app/Controllers/My/PluginController.php` |
| 마이그레이션 | `app/Database/Migrations/2026-08-15-000001_CreatePluginsTable.php`, `…000002_CreateUserPluginsTable.php` |
| 이벤트 규약 | `docs/plugin-events.md` |
| 예시 플러그인 | `app/Plugins/Quote`(Events 발행·구독 파일), `Pricing`, `CncEngine`(Services·마이그레이션·Routes), `AiQuote`(외부 API 클라이언트 인터페이스) |
