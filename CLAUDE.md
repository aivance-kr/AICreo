# CLAUDE.md

이 파일은 이 저장소에서 작업할 때 Claude Code(claude.ai/code)에 대한 가이드를 제공합니다.

> **공통 규칙은 전역 [`~/.claude/CLAUDE.md`](~/.claude/CLAUDE.md) 에서 자동 상속**된다(언어·Git 워크플로우·보안·코드 스타일·테스트·API·LSP). 이 문서는 **AiCreo 저장소 전용** 규칙만 정의한다.

## 저장소 개요

1인 웹 에이전시를 위한 CodeIgniter 4 기업 홈페이지 템플릿(게시판 CMS / 사이트 빌더)입니다 — 동적 페이지, 게시판 시스템, 문의 폼, 관리자 패널을 제공합니다.

저장소 루트가 하나의 CI4 프로젝트입니다. 모든 `php spark`, `composer`, `git` 명령은 루트에서 실행합니다.

> **PHP 8.5+ 필수** (`composer.json` `require`/`platform` 고정). PHPStan 레벨 6.

## 명령어

```bash
php spark serve --port 8306  # 개발 서버 실행 (http://localhost:8306)
php spark migrate            # 대기 중인 마이그레이션 전체 실행 (테이블 생성 + 시딩)
php spark migrate:rollback   # 마지막 마이그레이션 배치 롤백
```

**검증 게이트 — 어디서 무엇을 돌리는가.** 검증은 로컬에서 끝낸다. `feature → dev` PR 에는 CI 를 걸지 않고(코드 리뷰만), CI 는 `dev → main` 배포 PR 에서만 돈다.

```
feature/*  ──[로컬 검증: composer ci]──▶  dev  ──[PR + 코드 리뷰]──▶  dev → main PR ──[CI]──▶  main
                    ↑
              여기가 실질적 게이트 (feature → dev 는 Squash merge 라 CI 가 없다)
```

```bash
composer cs          # PHP-CS-Fixer 스타일 점검 (dry-run)
composer cs:fix      # 스타일 자동 정규화
composer analyse     # PHPStan 정적 분석 (레벨 6)
composer test        # PHPUnit (테스트 DB는 MySQL)
composer ci          # cs + analyse + test 한 번에 — push 전 이걸로 CI 미리 통과
composer rector:dry  # 코드 현대화 미리보기 (선택), composer rector 로 적용
```

| 시점 | 무엇을 |
|------|--------|
| 개발 중 | `composer analyse` + `composer test` 수시 실행 |
| push 전 (`main` 제외 모든 브랜치) | `composer ci` 필수 — 실패하면 push 하지 않는다. `pre-push` 훅이 강제한다 |
| `feature → dev` PR | CI 없음. 코드 리뷰만 — 직전 push 의 `composer ci` 가 유일한 방어선 |
| `dev → main` PR | GitHub Actions 전체(`quality` 잡: cs·analyse·test, PHP 8.5/MySQL 8.0 + `coverage` 잡: job summary 에 리포트) |

`feature → dev` 는 GitHub Squash merge 로 처리되어 로컬 훅도 CI 도 그 순간엔 동작하지 않는다 — 그래서 `feature/*` push 도 `dev` push 와 동일하게 `composer ci` 를 강제한다(건너뛰지 않는다). 이 단계를 생략하면 검증되지 않은 코드가 `dev` 에 쌓이고, 배포 PR 에서야 CI 가 처음 돌아 원인 추적 비용이 커진다 — 생략은 규칙 위반이다.

#### self-hosted 러너에서 돈다

GitHub 호스팅 러너(`ubuntu-latest`)가 아니라 **로컬 Mac을 self-hosted 러너로 등록해서** 돈다. 두 잡(`quality`, `coverage`) 모두 `runs-on: [self-hosted, macOS, ARM64]`.

- **러너 위치**: `~/actions-runners/AICreo`(저장소 밖). `aicreo-mac-local-runner` 라는 이름으로 launchd 서비스(`actions.runner.pushwing-AICreo.aicreo-mac-local-runner`)로 상시 등록돼 있다 — Mac이 켜져 있으면 자동으로 리스닝한다.
- **저장소가 Public** — self-hosted 러너에 `pull_request` 트리거가 걸려 있으면 외부 fork PR 코드가 러너에서 실행될 위험이 있어(공식적으로 알려진 위험), 저장소 설정에서 `fork-pr-contributor-approval` 을 `all_external_contributors` 로 켜 두었다. 외부 협업자의 PR은 관리자가 수동 승인하기 전까지 워크플로우가 실행되지 않는다.
- **MySQL**: self-hosted **macOS** 러너는 `services:` 도커 컨테이너를 지원하지 않는다(Linux 러너 전용 기능). 대신 각 잡에서 `docker run` 으로 직접 기동하고 `if: always()` 스텝으로 정리한다. Redis는 캐시 핸들러 기본값이 `file` 이라 CI 에 필요 없다.
- **포트**: 이 Mac은 여러 저장소의 self-hosted 러너를 동시에 호스팅한다. 시스템 mysqld(`3306`)·AIFid(`13306`)·AILicet/AITessera(`23306`) 등과 겹치지 않는 **MySQL `43306`** 을 쓴다. 새 포트를 고를 땐 `~/claude-works/*/.github/workflows/ci.yml` 을 함께 grep 해서 겹치는 값이 없는지 반드시 확인할 것 — 처음 `23306`으로 골랐다가 다른 두 저장소와 충돌해 CI 가 실패했었다.
- **호스팅 러너로 되돌리려면**: `runs-on` 을 `ubuntu-latest` 로 바꾸고 MySQL을 다시 `services:` 블록으로 되돌리면 된다(포트도 표준값 `3306`으로 원복 가능).

**Cron (운영 — 단 1줄 등록):**
```
* * * * * cd /path/to/app && php spark tasks:run >> /dev/null 2>&1
```
`Config/Tasks.php`가 `settings` 테이블에서 활성화된 잡을 읽어 등록. 활성화·주기는 `/admin/schedule`에서 관리.

## 초기 설정

```bash
cp env .env
# .env 편집: DB 접속 정보, CI_ENVIRONMENT, TinyMCE 키
php spark migrate
# app/Config/App.php: appTimezone = 'Asia/Seoul' 설정
```

기본 관리자 계정: `admin@example.com` / `admin1234!`

Linux 업로드 권한: `chmod -R 755 public/uploads writable`

**Git 훅 활성화 (클론 후 1회):**
```bash
git config core.hooksPath .githooks
```
- `.githooks/pre-commit` — 커밋 직전 스테이징된 PHP 파일에 PHP-CS-Fixer(`composer cs:fix` 규칙)를 자동 적용(커밋을 막지는 않음).
- `.githooks/pre-push` — 대상 브랜치별로 정책이 다르다:
  - `main` 직접 push는 **무조건 차단**(배포는 `dev → main` PR 로만).
  - 그 외 브랜치는 품질 게이트(`composer ci` = cs·analyse·test, ~10초)를 실행해 CI 왕복 전에 로컬에서 실패를 걸러냄.
  - 문서 전용 변경(`*.md`, `docs/**`, `.claude/rules/**` 만 바뀐 push)은 검증을 자동으로 건너뜀. 코드가 한 줄이라도 섞이면 즉시 전체 검증으로 돌아간다.
- 긴급 우회: `SKIP_HOOKS=1 git commit/push ...`(`main` 차단은 우회되지 않음). PHP·Composer 가 없는 환경에서는 해당 검증을 자동으로 건너뛴다.

## 상세 규칙 (모듈)

- **아키텍처** (테마 시스템, BaseController, 인증·라우팅, CSRF 예외, 캐싱, OAuth, 파일 업로드, DB 스키마): @.claude/rules/architecture.md
