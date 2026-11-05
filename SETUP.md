# AICreo 설치·서버 설정 가이드

> 기능 소개는 [`README.md`](README.md), 관리자 화면 사용법은 [`docs/manual.md`](docs/manual.md), 플러그인 만들기는 [`docs/plugin-development.md`](docs/plugin-development.md).

## 1. 설치

PHP **8.5 이상**, Composer, MySQL/MariaDB 가 필요합니다.

```bash
git clone <이 저장소> my-project
cd my-project
composer install
```

저장소 루트가 하나의 CI4 프로젝트입니다. 모든 `php spark`·`composer` 명령은 루트에서 실행합니다.

## 2. .env 설정

```bash
cp env .env
```

필수 항목:

```
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8306/'     # 비워 두면 CI4 가 예외를 던짐 — 배포 시 실제 URL
database.default.hostname = localhost
database.default.database = your_db
database.default.username = root
database.default.password = 
database.default.DBDriver = MySQLi
```

선택 항목 (필요한 기능을 쓸 때만):

| 키 | 용도 |
|----|------|
| `encryption.key` | **관리자 2단계 인증에 필수.** `php spark key:generate` 로 생성. 키를 바꾸면 저장된 시크릿을 복호화할 수 없으니 `Config\Encryption::$previousKeys` 로 이전 키 유지 |
| `i18n.enabled = ko,en` | 다국어 켜기 (기본은 `ko` 만 = 꺼짐). 언어를 추가하면 `app/Language/{언어}/Site.php` 도 추가 |
| `GROQ_API_KEY` · `GROQ_MODEL` | 글 AI 제안·시리즈 AI 추천·AI 태그 |
| `email.*` · `fromEmail` | 문의 알림·뉴스레터 SMTP (`fromEmail` 이 비면 사이트 설정 이메일) |
| `SLACK_INQUIRY_WEBHOOK_URL` | 문의 접수 즉시 Slack 알림. 관리자 "알림" 탭의 웹훅과 별개(둘 다 쓰면 두 번 감) |
| `security.ipWhitelist` | 자동/수동 차단에서 제외할 IP(쉼표 구분). 공개 저장소에 커밋하지 말고 서버 `.env` 에만 |
| `plugins.allowUpload = true` | 관리자 화면의 플러그인 **ZIP 업로드 허용**. 서버에 임의 PHP 를 올리는 기능이라 기본 꺼짐 |
| `INDEXNOW_KEY` | IndexNow 색인 제출 |
| `oauth.{google,naver,kakao}.client_id` · `client_secret` | 소셜 로그인 OAuth 키 (`/admin/settings/oauth` 에 콜백 URL 안내) |
| `editor.tinymce_api_key` | TinyMCE Cloud 를 쓸 때만. 기본은 키가 필요 없는 jsDelivr CDN |

## 3. 타임존

`app/Config/App.php`:

```php
public string $appTimezone = 'Asia/Seoul';
```

DB 에는 UTC 로 저장되고 앱·화면은 KST 입니다 (연결 세션 타임존 `+09:00` 이 변환). 시각 컬럼은 `TIMESTAMP` 만 사용합니다.

## 4. 마이그레이션

```bash
php spark migrate
```

테이블 생성과 기본 데이터(게시판 3개 + 관리자 계정)가 처리됩니다.

## 5. 기본 계정

- 이메일: `admin@example.com`
- 비밀번호: `admin1234!`

최초 로그인 후 반드시 비밀번호를 바꾸고 `/admin/security` 에서 2단계 인증을 켜세요.

## 6. 폴더 권한 (Linux)

```bash
chmod -R 755 public/uploads writable
```

웹 서버와 **같은 사용자**가 `writable/`(로그·캐시·백업·플러그인 활성 캐시)에 쓸 수 있어야 합니다.

## 7. 개발 서버

```bash
php spark serve --host 127.0.0.1 --port 8306
```

> `--host 127.0.0.1` 을 빼면 환경에 따라 IPv6(`::1`)에만 바인딩되어 리버스 프록시(`creo.test` 등)가 `502` 를 냅니다.

## 8. 서버 crontab (필수)

배포 후 **서버에서** 등록해야 하는 배치입니다. 없으면 큐에 쌓이기만 하고 메일·웹훅이 나가지 않으며, 예약 글이 공개되지 않고 휴지통이 비워지지 않습니다.

```cron
* * * * * cd /path/to/app && php spark posts:publish-scheduled >> /dev/null 2>&1
* * * * * cd /path/to/app && php spark newsletter:send         >> /dev/null 2>&1
* * * * * cd /path/to/app && php spark webhook:send            >> /dev/null 2>&1
0 * * * * cd /path/to/app && php spark ip:auto-block           >> /dev/null 2>&1
0 4 * * * cd /path/to/app && php spark trash:empty             >> /dev/null 2>&1
0 4 * * * cd /path/to/app && php spark admin-logs:purge        >> /dev/null 2>&1
```

- `/path/to/app` 은 실제 앱 경로, `php` 는 **PHP 8.5 CLI 의 전체 경로**(예: `/usr/bin/php8.5`)로 바꿉니다. 크론은 `PATH` 가 짧아 다른 버전의 `php` 가 잡힐 수 있습니다.
- 웹 서버와 같은 사용자로 등록합니다 (예: `sudo crontab -u www-data -e`).
- 뉴스레터는 1분 주기에 한 번 50건(시간당 최대 3,000건)입니다. 구독자가 많으면 `--limit 200` 처럼 올리되 SMTP 업체 한도를 넘지 않게 하세요. 실행이 겹쳐 중복 발송이 걱정되면 `flock -n /tmp/newsletter.lock php spark newsletter:send` 로 감쌉니다.
- 점검: `php spark newsletter:send` 를 수동으로 한 번 실행해 "완료: 발송 N건, 실패 M건" 이 나오면 정상입니다.

## 9. 플러그인

```bash
ls app/Plugins        # 출고 예시: Sample
```

1. 관리자 `/admin/plugins` → **동기화** (플러그인 폴더를 읽어 목록에 등록, 상태 비활성)
2. **활성화** → 마이그레이션 실행, 라우트·이벤트 로드
3. 비활성화(데이터 유지) / 제거(마이그레이션 롤백, **데이터 삭제**)

> 활성 상태의 부팅용 캐시 `writable/plugins/active.php` 는 git 에 없고 **서버별 상태**입니다. **새 서버·클린 배포 직후에는 전부 비활성**이므로 `/admin/plugins` 에서 동기화·활성화를 다시 해야 합니다.
>
> ZIP 업로드 설치는 `.env` 에 `plugins.allowUpload = true` 일 때만 열리며, `writable/plugins_tmp/` 와 `app/Plugins/` 에 쓰기 권한이 필요합니다.

개발 방법은 [`docs/plugin-development.md`](docs/plugin-development.md).

## 10. Git 훅 (개발자)

클론 후 한 번:

```bash
git config core.hooksPath .githooks
```

- `pre-commit`: 스테이징된 PHP 파일에 PHP-CS-Fixer 자동 적용
- `pre-push`: `composer ci` 검증 (`main` 직접 푸시는 항상 차단, 문서만 바뀐 푸시는 생략)
- 긴급 우회: `SKIP_HOOKS=1 git push …`

## 11. 테스트 (PHPUnit)

운영과 동일한 MySQL 스키마로 테스트하므로 **운영 DB와 분리된 테스트 DB**가 필요합니다. 테스트는 매 실행 데이터를 초기화합니다.

```bash
# 1) 테스트 DB 생성 (운영 DB와 다른 이름)
php spark db:create ci4-agency_test

# 2) .env 에 테스트 DB 자격증명 추가
#   database.tests.hostname = localhost
#   database.tests.database = ci4-agency_test
#   database.tests.username = <user>
#   database.tests.password = <pass>
#   database.tests.DBDriver = MySQLi

# 3) 실행
composer test        # = phpunit
composer analyse     # PHPStan (레벨 6)
composer cs          # 코드 스타일 점검 (cs:fix 로 자동 수정)
composer ci          # cs + analyse + test — 푸시 전 필수
composer coverage    # 커버리지(텍스트 + build/coverage-html/) — Xdebug/pcov 필요
```

> 테스트 환경에서는 플러그인 활성 캐시로 `writable/plugins/active_test.php` 를 써서 개발용 `active.php` 를 건드리지 않습니다.
>
> CI(GitHub Actions)는 `dev → main` 배포 PR 에서만 돕니다 (`feature → dev` 는 로컬 `composer ci` 가 유일한 관문).

## 12. 배포

`main` 에 푸시되면 `deploy.yml` 이 서버에서 `git reset --hard origin/main` → `composer install --no-dev` → `php spark migrate --all` → `cache:clear` 를 수행합니다. 마이그레이션은 배포에 포함되어 있어 따로 실행하지 않습니다 (`--all` 은 비활성 플러그인 테이블도 만듭니다).

## URL 구조

| URL | 설명 |
|-----|------|
| `/board/{slug}` | 게시판 목록 (기본: `notice` · `free` · `qna`) |
| `/board/{slug}/write` | 글쓰기 |
| `/board/{slug}/{id}` | 글 보기 |
| `/board/file/{id}/download` | 파일 다운로드 |
| `/series` · `/tags` · `/archive` · `/search` | 연재·태그·아카이브·검색 |
| `/feed` · `/rss` · `/sitemap.xml` · `/robots.txt` · `/llms.txt` | RSS·SEO/GEO |
| `/auth/login` · `/auth/register` · `/auth/profile` | 로그인·회원가입·프로필 |
| `/my/plugins` | 내 플러그인 (회원) |
| `/admin` | 관리자 대시보드 |
| `/admin/boards` | 게시판 관리 |
| `/admin/plugins` | 플러그인 관리 |
| `/admin/security` | 2단계 인증 |
| `/en/...` | 다국어를 켠 경우 언어별 공개 화면 |
