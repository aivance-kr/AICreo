# 플러그인 개발 가이드

AICreo 코어를 건드리지 않고 기능을 **독립 모듈(플러그인)** 로 추가·제거하는 방법을 처음부터 끝까지 따라 하며 익히는 문서다.

- **이 문서**: 플러그인을 *만드는 사람*을 위한 실무 가이드 (디렉터리, 매니페스트, 라우트, 마이그레이션, 뷰, 이벤트, 권한, 테스트, 패키징).
- **설계 배경·함정**: [`plugin-architecture.md`](plugin-architecture.md) — 왜 이렇게 만들었는지, 부팅 순서, 코어 수정 지점.
- **이벤트 규약**: [`plugin-events.md`](plugin-events.md).
- **동작하는 예시**: [`app/Plugins/Sample`](../app/Plugins/Sample) — 이 가이드의 모든 구조를 담은 최소 플러그인.

---

## 1. 한눈에 보기

```
app/Plugins/Sample/                  ← 디렉터리명 = key 의 PascalCase
├── plugin.json                      ← 필수. 이것이 있어야 "플러그인"으로 인식
├── Routes.php                       ← 선택. 라우트 (활성일 때만 로드)
├── Events.php                       ← 선택. 이벤트 구독 (활성일 때만 로드)
├── Controllers/SampleController.php
├── Models/SampleNoteModel.php
├── Libraries/NoteAddedListener.php
├── Views/index.php
└── Database/Migrations/…            ← 활성화 시 실행, 제거 시 롤백
public/plugins/sample/               ← CSS·JS·이미지 (선택)
```

| 동작 | 라우트·이벤트 | 마이그레이션 | 테이블 데이터 |
|------|:---:|:---:|:---:|
| 동기화(sync) | — | — | — (파일을 읽어 `plugins` 테이블에 등록, 상태 `inactive`) |
| 활성화 | 로드 | **실행** | 생성 |
| 비활성화 | 내림 | 유지 | **유지** |
| 제거 | 내림 | **롤백(`down()`)** | **삭제** |

플러그인 코드는 `Plugins\{Name}` 네임스페이스다 (`App\Plugins` 가 **아니다** — 플러그인별 마이그레이션을 분리 실행하려고).

---

## 2. 5분 만에 플러그인 만들기

예: `memo` 플러그인 — 메모를 저장하고 `/memo` 에 보여준다.

### 2-1. 디렉터리와 매니페스트

```
app/Plugins/Memo/plugin.json
```

```json
{
  "key": "memo",
  "name": "메모",
  "version": "1.0.0",
  "license_type": "onetime",
  "menu": [
    { "title": "메모", "route": "memo", "icon": "bi-journal" }
  ]
}
```

### 2-2. 마이그레이션

`app/Plugins/Memo/Database/Migrations/2026-11-05-100001_CreateMemoNotesTable.php`

```php
<?php

declare(strict_types=1);

namespace Plugins\Memo\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateMemoNotesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'body'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at' => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('memo_notes');
    }

    public function down(): void
    {
        $this->forge->dropTable('memo_notes');   // 제거 시 실제로 실행된다 — 반드시 구현
    }
}
```

### 2-3. 모델·컨트롤러·뷰·라우트

```php
// Models/MemoNoteModel.php
namespace Plugins\Memo\Models;

use CodeIgniter\Model;

class MemoNoteModel extends Model
{
    protected $table         = 'memo_notes';
    protected $returnType    = 'array';
    protected $allowedFields = ['body'];
    protected $useTimestamps = false;
}
```

```php
// Controllers/MemoController.php
namespace Plugins\Memo\Controllers;

use App\Controllers\BaseController;
use Plugins\Memo\Models\MemoNoteModel;

class MemoController extends BaseController
{
    public function index(): string
    {
        return $this->render('\Plugins\Memo\Views\index', [
            'page'  => ['title' => '메모', 'meta_title' => '메모', 'meta_desc' => '', 'noindex' => true],
            'notes' => model(MemoNoteModel::class)->orderBy('id', 'DESC')->findAll(20),
        ]);
    }
}
```

```php
// Views/index.php
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="container py-4">
    <?php foreach ($notes as $note): ?>
        <p><?= esc($note['body']) ?></p>
    <?php endforeach; ?>
</div>
<?= $this->endSection() ?>
```

```php
// Routes.php  (플러그인 루트 — Config/Routes.php 가 아니다)
use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('memo', '\Plugins\Memo\Controllers\MemoController::index');
```

### 2-4. 켜기

1. 관리자 `/admin/plugins` → **동기화** (새 플러그인이 `inactive` 로 목록에 뜬다)
2. **활성화** → 마이그레이션이 실행되고 `/memo` 가 열린다
3. 확인이 끝났으면 **비활성화**(데이터 유지) 또는 **제거**(테이블까지 삭제)

---

## 3. `plugin.json` 규칙

| 필드 | 필수 | 규칙 |
|------|:---:|------|
| `key` | ✅ | `^[a-z][a-z0-9_]*$`, 32자 이내, 전역 유일. **디렉터리명은 이 값의 PascalCase** (`ai_quote` → `AiQuote`) — 어긋나면 검증 실패 |
| `name` | ✅ | 1~64자 (관리자 화면 표시명) |
| `version` | ✅ | `MAJOR.MINOR.PATCH` |
| `license_type` | ✅ | `onetime` · `subscription` · `credit` |
| `requires.core` | ❌ | 제약 문자열. 현재는 **문법만 검증**하고 해석하지 않는다 |
| `requires.plugins` | ❌ | 선행 플러그인 key 배열. "없으면 동작하지 않음" 선언일 때만 (§8) |
| `menu[]` | ❌ | `{title, route, icon}`. `route` 는 선행 `/` 금지, `icon` 은 Bootstrap Icons 클래스 |

- 매니페스트는 **로드 시점에 전체 검증**된다. 하나라도 틀리면 그 플러그인만 건너뛰고 나머지는 정상 동작한다.
- 재스캔(동기화)은 `name`·`version`·`manifest` 만 갱신한다. 운영자가 정한 상태·가격·`license_type` 은 덮어쓰지 않는다 — 매니페스트의 `license_type` 은 최초 등록 시 **기본값 제안**일 뿐이다.

---

## 4. 라우트

```php
// app/Plugins/Memo/Routes.php
$routes->get('memo', '\Plugins\Memo\Controllers\MemoController::index');
$routes->group('admin/memo', ['filter' => 'auth:admin'], static function ($routes): void {
    $routes->post('store', '\Plugins\Memo\Controllers\MemoController::store');
});
```

- 컨트롤러는 **선행 `\` 를 붙인 전체 네임스페이스**로 쓴다.
- 파일 이름은 반드시 플러그인 **루트의 `Routes.php`**. `Config/Routes.php` 로 두면 CI4 자동 탐색이 코어 라우트 뒤에서 수집해서 `(:segment)` catch-all(동적 페이지 404)에 가려진다.
- 코어가 catch-all **직전에** 활성 플러그인의 `Routes.php` 를 명시적으로 로드하므로 우선순위 걱정은 없다.
- 라우트 URL 첫 세그먼트를 **언어 코드(`ko`, `en` …)와 겹치지 않게** 한다. 플러그인 라우트는 언어 접두어 없는 단일 경로로만 등록된다 (다국어 미지원).
- 인증 필터: `auth:admin`(관리자) · `auth:member`(회원) · `entitlement:{key}`(§7, 보유자만).
- POST 는 전역 CSRF 를 그대로 받는다. 폼에 `<?= csrf_field() ?>` 를 넣는다. 토큰 없는 POST 를 허용하려고 코어 `Config/Filters.php` 제외 목록을 고치지 않는다.
- 주소에 **토큰이 들어가는 경로**(공유 링크 등)는 방문 로그에 토큰이 남지 않도록 `VisitLogFilter` 제외 목록에 추가해야 한다 (`preview/*`, `newsletter/*` 선례) — 코어 수정이 필요하니 가급적 쿼리·세션으로 전달하는 설계를 택한다.

---

## 5. 컨트롤러·뷰

- 컨트롤러는 `App\Controllers\BaseController` 를 상속한다. `$settings`·`$menus`·`$authUser` 등 전역 데이터가 `render()` 로 자동 병합된다.
- 뷰 렌더: `$this->render('\Plugins\Memo\Views\index', [...])` — **네임스페이스 절대 경로**. 파일은 `Views/index.php`.
- 컨트롤러는 얇게: 입력 검증 → 모델/서비스 호출 → 응답. 비즈니스 로직은 서비스·모델로.
- 출력은 전부 `esc()`. SQL 은 쿼리 빌더/바인딩만.
- 뷰가 `<?= $this->extend('layouts/main') ?>` 를 쓰면 **활성 테마의 레이아웃**이 그대로 적용된다 (ThemeView 가 `themes/{활성}/layouts/main` → `themes/default/layouts/main` 순으로 찾는다). 단, 플러그인 *자신의* 뷰 파일은 테마가 교체할 수 없다 (네임스페이스 경로는 테마 탐색을 거치지 않음).
- 관리자 화면이 필요하면 `layouts/admin` 을 extend 하고 `/admin/...` 아래에 `auth:admin` 으로 둔다.
- 메시지는 레이아웃 상단의 공통 플래시 알림을 쓴다 — 뷰에서 `session()->getFlashdata()` 를 다시 출력하지 않는다 (중복 표시).

### 정적 자산

`public/plugins/{key}/` 아래에 둔다 (ZIP 패키지에서는 플러그인 안 `public/` 이 이 위치로 이동). 뷰에서는 `base_url('plugins/memo/memo.css')`.

---

## 6. 데이터베이스

- **테이블 접두어**로 충돌 방지: `{key}_` (`memo_notes`, `pricing_materials`).
- 시각 컬럼은 **`TIMESTAMP`** 만 (`DATETIME` 금지 — 저장은 UTC, 앱·화면은 KST 로 DB 연결 세션이 자동 변환하는 프로젝트 규약). 날짜만이면 `DATE`.
- 마이그레이션 파일은 `Database/Migrations/`, 클래스 네임스페이스는 `Plugins\{Name}\Database\Migrations`.
- `down()` 은 **필수**. 제거 시 실제로 실행되어 데이터가 사라진다.
- 모델은 `$allowedFields` 를 명시한다.
- 사용자별 데이터는 §7-1 의 `UserScopedModel` 트레이트를 쓴다.
- 마이그레이션은 **활성화할 때** 실행된다. 단 배포 스크립트의 `php spark migrate --all` 은 비활성 플러그인 테이블도 만든다 (무해하지만 알아 둘 것).

수동 실행이 필요하면:

```bash
php spark migrate -n "Plugins\Memo"
```

---

## 7. 회원별 권한 — Entitlement (선택)

"관리자가 켜는 기능"이면 필요 없다. 플러그인을 **회원에게 판매·부여**할 때만 쓴다.

```php
$routes->group('my/memo', ['filter' => 'entitlement:memo'], static function ($routes): void {
    $routes->get('/', '\Plugins\Memo\Controllers\MemoController::index');
    $routes->post('store', '\Plugins\Memo\Controllers\MemoController::store');
});
```

- `entitlement:{key}` 하나로 통일한다. 이 필터가 **로그인 검사까지 포함**하므로 `auth:member` 를 겹쳐 걸지 않는다.
- 접근 판정(`AccessLevel`): `Full` 전부 허용 · `ReadOnly` 조회만 · `None` `/my/plugins` 로 이동.
- **만료된 구독 = `ReadOnly`**. 데이터를 지우거나 잠그지 않고 조회만 허용하고 `POST/PUT/PATCH/DELETE` 를 차단한다. 따라서 **GET 핸들러는 DB 에 쓰지 않는다** (규약).
- 만료는 DB 에 저장하지 않고 `expires_at` 으로 매번 계산한다 (경계는 만료 시각까지 사용 가능).
- 컨트롤러 안에서 권한 체크를 직접 구현하지 않는다. 판정은 `App\Libraries\Plugin\Entitlement` 한 곳에만 있다.
- 회원은 `/my/plugins` 에서 보유 플러그인 목록 확인·구독 해지·(크레딧형) 활성화를 한다. 이 경로는 `entitlement` 필터 **밖**에 있다 (안에 두면 무한 리다이렉트).

### 7-1. `UserScopedModel` — 사용자 데이터 격리

회원별 데이터 테이블은 `user_id` 컬럼을 두고 모델에 트레이트를 붙인다.

```php
use App\Models\Traits\UserScopedModel;

class MemoNoteModel extends Model
{
    use UserScopedModel;
    // before* 콜백 프로퍼티를 직접 선언하지 않는다 (트레이트가 점유)
}
```

- 조회·저장·수정·삭제에서 `user_id` 를 **자동 주입·필터**한다.
- 사용자를 결정할 수 없으면 조용히 넘어가지 않고 `UserScopeMissingException`.
- CLI·워커·테스트: `->forUser($id)`. 정당한 전역 조회(관리자): `->withoutUserScope(fn ($m) => …)` — 우회를 한 곳으로 모아 추적 가능하게 한다.
- `user_id` 필터 누락은 버그가 아니라 **보안 결함**이다. 타 사용자 데이터 접근 불가를 테스트로 고정한다.

---

## 8. 플러그인 간 통신 — Events

플러그인끼리는 서로의 클래스를 `use` 하지 않는다. 필요하면 CI4 `Events` 로만 소통한다. 상세 규약은 [`plugin-events.md`](plugin-events.md).

```php
// 발행 (쓰기가 성공한 뒤)
Events::trigger('memo.note_added', ['note_id' => $id]);
```

```php
// 구독: 플러그인 루트 Events.php (활성일 때만 로드됨)
Events::on('memo.note_added', [NoteAddedListener::class, 'handle']);
```

- 이벤트명 `{key}.{과거형 동사}`, 페이로드는 snake_case 배열(식별자 위주), 키는 **추가만** 가능.
- 구독자는 **놓쳐도 되는 후속 작업**만. 반드시 실행돼야 하면 발행자 책임이거나 큐로.
- `requires.plugins` 는 "없으면 동작하지 않음"일 때만. 이벤트를 듣기만 하는 관계는 넣지 않는다.

---

## 9. 코어 기능 재사용

| 필요 | 쓸 것 |
|------|-------|
| 파일·이미지 업로드 | `FileUploader`·`ImageUploader`·`MediaUploader` (확장자 화이트리스트·랜덤 파일명 내장) |
| 캐시 | `cache()` — 키는 `{key}_…` 접두어 |
| 외부 API | 타임아웃 5초, 인터페이스 + 서비스 등록 → 테스트에서 fake 교체 |
| 시간이 걸리는 작업(메일·리포트) | 요청 사이클에서 처리하지 말고 큐 테이블 + Spark 커맨드 (뉴스레터·웹훅 큐 방식 참고) |
| 관리자 활동 기록 | `AdminLogModel::record()` (민감 값 제외) |
| 웹훅·알림 | `WebhookDeliveryModel::enqueue()` |

---

## 10. 테스트

플러그인도 코어와 같은 품질 게이트(`composer ci`)를 통과해야 한다. 테스트는 `tests/` 아래에 둔다 (DB 가 필요하면 `Tests\Support\DatabaseTestCase`).

- **테스트 환경은 개발 캐시를 건드리지 않는다.** `pluginManager` 서비스가 테스트 때 `writable/plugins/active_test.php` 를 쓴다. 경로는 항상 `WRITEPATH` 경유 (paratest 병렬이면 워커별로 갈린다).
- `PluginManager` 는 생성자로 캐시·플러그인 디렉터리를 주입받는다 → 임시 디렉터리에 `tests/_support/Plugins/Valid` 를 복사해 격리 (`tests/feature/Plugin/PluginRoutesTest.php` 참고).
- 최소로 확보할 것:
  - 정상/실패 매니페스트
  - 활성화 시 마이그레이션 실행, **제거 시 코어 테이블이 남아 있는지**
  - 라우트가 활성일 때만 열리고 catch-all 보다 먼저 매칭되는지
  - 사용자 데이터 격리 (`UserScopedModel`), 만료 시 읽기 전용
- 출고 예시의 통합 테스트: `app/Plugins/Sample` 전 경로(스캔 → 활성화 → 마이그레이션 → 라우트 → 렌더 → 제거).

```bash
composer test     # 전체
composer ci       # cs + 정적분석(PHPStan level 6) + 테스트 — 푸시 전 필수
```

---

## 11. ZIP 패키징·배포

관리자 `/admin/plugins` 의 ZIP 업로드로 설치한다. 패키지 구조는 **최상위에 `plugin.json`**:

```
memo.zip
├── plugin.json              ★ 필수 (최상위)
├── Routes.php
├── Events.php
├── Controllers/ Models/ Views/ Libraries/
├── Database/Migrations/
└── public/                  → public/plugins/memo/ 로 이동
    └── memo.css
```

설치기는 **전부 검증을 통과하기 전에는 목적지에 아무것도 쓰지 않는다**:

| 검사 | 내용 |
|------|------|
| 규모 | 엔트리 2000개 이하, 압축 해제 총량 50MB 이하 |
| Zip-slip | `..`·선행 `/`·`\`·드라이브 접두어(`C:`) 거부 |
| 확장자 화이트리스트 | `php json css js map md png jpg jpeg gif svg webp ico woff woff2 ttf` 외 거부 |
| 매니페스트 | 최상위 `plugin.json` 필수, 검증 통과 |

- 설치 디렉터리명은 ZIP 안의 폴더 구조가 아니라 **`key` 에서 유도**한다 (ZIP 을 신뢰하지 않음).
- 같은 key 가 이미 있으면 교체된다. 설치 직후 상태는 항상 **비활성** — 업로드가 곧 활성화는 아니다.

> ⚠️ **ZIP 설치는 서버에 임의 PHP 를 올리는 기능**이라 기본으로 꺼져 있다. 켜려면 서버 `.env` 에 `plugins.allowUpload = true` (`Config\Plugins`). 관리자 화면(DB)이 아니라 `.env` 인 이유는 관리자 계정이 탈취돼도 웹에서 스스로 켜 코드를 올리지 못하게 하려는 것이다. 신뢰하는 관리자만 쓰고, 불필요하면 꺼 둔다. 꺼져 있으면 업로드 폼이 보이지 않고 `/admin/plugins/upload` 는 거절한다.
>
> 업로드 설치에는 `writable/plugins_tmp/` 와 `app/Plugins/` 쓰기 권한이 필요하다 (읽기 전용 배포에서는 불가 — 소스를 직접 배치한 뒤 동기화한다).

---

## 12. 운영 주의

- 활성 상태의 **부팅용 투영**은 `writable/plugins/active.php` (git 에 없음). 운영 상태의 진실은 DB `plugins` 테이블이다. **새 서버·클린 배포 직후에는 전부 비활성**이므로 `/admin/plugins` 에서 동기화 후 활성화한다.
- 이 파일은 서버별 상태다 — 다중 서버면 서버마다 맞춰야 한다.
- `missing` 상태(플러그인 파일이 사라짐)는 레코드·가격 이력을 보존하며 활성화할 수 없다. 파일을 복구하고 동기화하면 `inactive` 로 돌아온다.
- 제거는 마이그레이션을 롤백한다 — **데이터가 사라지는 유일한 경로**다. 운영에서는 비활성화로 충분한지 먼저 본다.

---

## 13. 체크리스트

- [ ] 디렉터리명 = `key` 의 PascalCase, `plugin.json` 검증 통과
- [ ] 마이그레이션: 테이블 접두어, `TIMESTAMP`, `down()` 구현
- [ ] `Routes.php`/`Events.php` 는 플러그인 루트, 컨트롤러는 `\` 시작 전체 네임스페이스
- [ ] 다른 플러그인 클래스 `use` 없음 (이벤트로만 통신)
- [ ] 출력 `esc()`, SQL 바인딩, POST 에 CSRF 토큰
- [ ] 회원별 데이터면 `UserScopedModel` + 격리 테스트, 판매형이면 `entitlement:{key}` 만 사용
- [ ] GET 핸들러에서 DB 쓰기 없음
- [ ] 테스트 작성, `composer ci` 통과
- [ ] 관리자 화면에서 동기화 → 활성화 → 접근 → 비활성화 → 제거 확인
