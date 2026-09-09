# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working in this repository.

> **Common rules are automatically inherited from the global [`~/.claude/CLAUDE.md`](~/.claude/CLAUDE.md)** (language, Git workflow, security, code style, testing, API, LSP). This document defines only rules **specific to the AiCreo repository**.

## Repository Overview

A CodeIgniter 4 corporate homepage template (board CMS / site builder) for solo web agencies — provides dynamic pages, a board system, inquiry forms, and an admin panel.

The repository root is a single CI4 project. Run all `php spark`, `composer`, and `git` commands from the root.

> **PHP 8.5+ required** (pinned in `composer.json` `require`/`platform`). PHPStan level 6.

## Commands

```bash
php spark serve --host 127.0.0.1 --port 8306  # Run the dev server (http://creo.test, via Caddy reverse proxy)
php spark migrate            # Run all pending migrations (create tables + seed)
php spark migrate:rollback   # Roll back the last migration batch
```

> ⚠️ **Omitting `--host` causes access to `creo.test` to fail with `502 Bad Gateway`.** Without `--host`, binding to the default `localhost` only listens on IPv6 (`::1`) in this macOS environment, but the shared Caddy that proxies `creo.test` (`~/claude-works/dev-proxy/Caddyfile`) tries to connect via `127.0.0.1:8306` (IPv4) and is refused. Always specify `--host 127.0.0.1`.

**Verification gate — what runs where.** Verification finishes locally. No CI runs on `feature → dev` PRs (code review only); CI runs only on `dev → main` deployment PRs.

```
feature/*  ──[local verification: composer ci]──▶  dev  ──[PR + code review]──▶  dev → main PR ──[CI]──▶  main
                    ↑
              This is the real gate (feature → dev is a Squash merge, so there's no CI there)
```

```bash
composer cs          # PHP-CS-Fixer style check (dry-run)
composer cs:fix      # Auto-normalize style
composer analyse     # PHPStan static analysis (level 6)
composer test        # PHPUnit (test DB is MySQL)
composer ci          # cs + analyse + test all at once — pass CI preemptively with this before push
composer rector:dry  # Preview code modernization (optional), apply with composer rector
```

| Timing | What |
|------|--------|
| During development | Run `composer analyse` + `composer test` frequently |
| Before push (any branch except `main`) | `composer ci` is required — don't push if it fails. Enforced by the `pre-push` hook |
| `feature → dev` PR | No CI. Code review only — the `composer ci` from the last push is the only line of defense |
| `dev → main` PR | Full GitHub Actions (`quality` job: cs·analyse·test, PHP 8.5/MySQL 8.0 + `coverage` job: report in job summary) |

#### Deployment runs automatically on push to `main`

`deploy.yml` runs on `push: branches: [main]` and performs, via SSH, `git reset --hard origin/main` → `composer install --no-dev` → `php spark migrate --all` → `cache:clear` on the production server. **Migrations run automatically as part of deployment, so there's no need to run them separately.**

> ⚠️ Previously, deployment was gated on `workflow_run: workflows:[CI], branches:[main]`, running only after CI succeeded. But when the CI trigger changed to `pull_request: [main]` (#244), **this connection silently broke** — a CI run triggered by `pull_request` belongs to the head branch (`dev`), so it doesn't match the `branches: [main]` filter. As a result, deployment never ran after 2026-07-16 — merges succeeded normally, but the production server was left running old code (discovered after the #255 deployment). **Whenever touching the CI trigger, always check whether `deploy.yml` depends on it.**

`feature → dev` is handled via GitHub Squash merge, so neither the local hook nor CI runs at that moment — which is why `feature/*` pushes enforce `composer ci` the same as `dev` pushes (it isn't skipped). Skipping this step lets unverified code accumulate in `dev`, and CI only catches it for the first time at the deployment PR, making root-cause tracing expensive — skipping it is a rule violation.

#### Runs on a self-hosted runner

Instead of a GitHub-hosted runner (`ubuntu-latest`), this runs on **a single self-hosted Linux (X64) runner belonging to the `aivance-kr` organization** (migrated to the organization in 2026-09 · from a personal macOS runner to an organization Linux runner). The `quality`, `coverage`, `notify`, and `deploy` jobs all use `runs-on: [self-hosted, Linux, X64]`.

> ⚠️ Actual infrastructure details such as runner registration location and service name are not yet reflected in this document — whoever set up the organization runner should fill in this section with the real values (registration path, service management method, etc.).

- **The repository is Public** — since a `pull_request` trigger on a self-hosted runner risks running external fork PR code on the runner (a well-known risk), `fork-pr-contributor-approval` is set to `all_external_contributors` in the repository settings. Workflows don't run for external contributors' PRs until an admin manually approves them.
- **MySQL**: the self-hosted **Linux** runner supports `services:` Docker containers. However, since one organization runner is shared across multiple repositories/workflows, each job still boots MySQL directly with `docker run` (to avoid conflicts from pinning the standard service port `3306`) and cleans up with an `if: always()` step. Redis isn't needed for CI since the cache handler default is `file`.
- **Ports**: multiple repositories can share the single organization runner. **The `quality` job uses MySQL port `43306`, and the `coverage` job uses `43307`.** When picking a new port, always grep other repositories' `.github/workflows/ci.yml` too to make sure there's no overlap — the port was initially chosen as `23306` but conflicted with another repository and caused CI to fail (a history from the personal macOS runner era, but the same principle applies after moving to the organization runner).
- **Container cleanup must use `docker rm -f -v`.** Without `-v`, only the container is removed and the `/var/lib/mysql` anonymous volume is left behind. It accumulates hundreds of MB per run, filling up the runner's Docker disk, and one day MySQL suddenly fails to even start with "No space left on device." In fact, 210 volumes (44GB) accumulated over a month starting 2026-07-17, blocking the PR #255 deployment CI. Recovery when blocked: `docker volume prune -f` (doesn't touch volumes in use).
- **With only one runner, jobs are effectively serialized.** `quality`/`coverage` have no `needs` dependency between them, but since the single shared runner processes only one job at a time, they're automatically queued and never run concurrently. Even so, each job still uses a different host port, because it could still overlap with another repository's or workflow's run — if ports collide, the later container fails to bind and dies immediately, but `docker run -d` prints a container ID and exits looking successful before that happens, so **the cause is obscured and only shows up as hundreds of "Connection refused" test failures.** This actually happened in PR #255, where only `quality` failed this way. Now each job checks that its container is alive and ready right after starting it, and aborts immediately with `docker logs` output if not.
- **To revert to a hosted runner**: change `runs-on` to `ubuntu-latest` (since it's Linux, MySQL can optionally be switched back to a `services:` block, and the port can be reverted to the standard `3306`).

**Cron (production — a single line registration):**
```
* * * * * cd /path/to/app && php spark tasks:run >> /dev/null 2>&1
```
`Config/Tasks.php` reads enabled jobs from the `settings` table and registers them. Enable/disable and intervals are managed at `/admin/schedule`.

## Initial Setup

```bash
cp env .env
# Edit .env: DB connection info, CI_ENVIRONMENT, TinyMCE key, app.baseURL (leave the local default at 8306,
#            but be sure to change it to the actual URL if running on a port other than 8306 or when deploying —
#            CI4 doesn't auto-detect and throws an exception if baseURL is missing or an empty string)
php spark migrate
# app/Config/App.php: set appTimezone = 'Asia/Seoul'
```

Default admin account: `admin@example.com` / `admin1234!`

Linux upload permissions: `chmod -R 755 public/uploads writable`

**Enable Git hooks (once after cloning):**
```bash
git config core.hooksPath .githooks
```
- `.githooks/pre-commit` — automatically applies PHP-CS-Fixer (`composer cs:fix` rules) to staged PHP files right before commit (doesn't block the commit).
- `.githooks/pre-push` — policy differs by target branch:
  - Direct push to `main` is **unconditionally blocked** (deployment only via `dev → main` PR).
  - Other branches run the quality gate (`composer ci` = cs·analyse·test, ~10 seconds) to catch failures locally before the CI round trip.
  - Docs-only changes (pushes touching only `*.md`, `docs/**`, `.claude/rules/**`) automatically skip verification. If even a single line of code is mixed in, it immediately reverts to full verification.
- Emergency bypass: `SKIP_HOOKS=1 git commit/push ...` (the `main` block is not bypassed). Environments without PHP/Composer automatically skip that verification.

## Detailed Rules (Modules)

- **Architecture** (theme system, BaseController, auth & routing, CSRF exceptions, caching, OAuth, file uploads, DB schema): @.claude/rules/architecture.md
