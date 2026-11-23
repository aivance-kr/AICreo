# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working in this repository.

> **Common rules are inherited automatically from the global [`~/.claude/CLAUDE.md`](~/.claude/CLAUDE.md)** (language, Git workflow, security, code style, testing, API, LSP). This document defines **AICreo repo-specific** rules only.
>
> **Exception:** mid-task progress messages are written in **English**; only the final result summary is written in **Korean**.

## Repository overview

A CodeIgniter 4 corporate website template (board CMS / site builder) for a solo web agency — provides dynamic pages, a board system, inquiry forms, and an admin panel.

The repo root is a single CI4 project. Run all `php spark`, `composer`, and `git` commands from the root.

> **PHP 8.5+ required** (pinned in `composer.json` `require`/`platform`). PHPStan level 6.

## Commands

```bash
php spark serve --host 127.0.0.1 --port 8306  # run dev server (http://creo.test, via Caddy reverse proxy)
php spark migrate            # run all pending migrations (create tables + seed)
php spark migrate:rollback   # roll back the last migration batch
```

> ⚠️ **Omitting `--host` causes `creo.test` access to fail with `502 Bad Gateway`.** Without `--host`, binding to the default `localhost` on this macOS environment listens only on IPv6 (`::1`), while the shared Caddy proxying `creo.test` (`~/claude-works/dev-proxy/Caddyfile`) tries to connect via `127.0.0.1:8306` (IPv4) and gets refused. Always specify `--host 127.0.0.1`.

**Verification gate — where what runs.** Verification finishes locally. `feature → dev` PRs don't run CI (code review only); CI runs only on `dev → main` deploy PRs.

```
feature/*  ──[local verification: composer ci]──▶  dev  ──[PR + code review]──▶  dev → main PR ──[CI]──▶  main
                    ↑
              this is the actual gate (feature → dev is a Squash merge, so no CI runs there)
```

```bash
composer cs          # PHP-CS-Fixer style check (dry-run)
composer cs:fix      # auto-normalize style
composer analyse     # PHPStan static analysis (level 6)
composer test        # PHPUnit (test DB is MySQL)
composer ci          # cs + analyse + test in one go — pre-clear CI before push
composer rector:dry  # preview code modernization (optional), apply with composer rector
```

| When | What |
|------|--------|
| During development | Run `composer analyse` + `composer test` as needed |
| Before push (every branch except `main`) | `composer ci` is required — don't push if it fails. Enforced by the `pre-push` hook |
| `feature → dev` PR | No CI. Code review only — the `composer ci` from the last push is the only safeguard |
| `dev → main` PR | Full GitHub Actions (single `quality` job: cs, analyse, test with coverage on PHP 8.5/MySQL 8.0; coverage report goes to the job summary) |

#### Deployment runs automatically on a `main` push

`deploy.yml` runs on `push: branches: [main]`, SSHing into the production server to do `git reset --hard origin/main` → `composer install --no-dev` → `php spark migrate --all` → `cache:clear`. **Migrations run automatically as part of deployment, so there's no need to run them separately.**

> ⚠️ It used to trigger deployment after CI succeeded via `workflow_run: workflows:[CI], branches:[main]`. But when the CI trigger changed to `pull_request: [main]` (#244), **this link quietly broke** — a CI run triggered by `pull_request` has its branch set to the head (`dev`), so it doesn't match the `branches: [main]` filter. As a result, deployment hadn't run even once since 2026-07-16 — merges succeeded normally, but the production server was left on old code (discovered after the #255 deploy). **Whenever you touch CI triggers, always check whether `deploy.yml` depends on them.**

Since `feature → dev` goes through a GitHub Squash merge, neither the local hook nor CI runs at that moment — so `feature/*` pushes enforce `composer ci` the same as `dev` pushes (never skipped). Skipping this step lets unverified code pile up on `dev`, with CI only catching it at the deploy PR stage where root-causing costs much more — skipping it is a rule violation.

#### Runs on a self-hosted runner

Runs not on GitHub-hosted runners (`ubuntu-latest`) but on **the self-hosted Linux (X64) runners of the `aivance-kr` organization** (runner-01~03, Default runner group, shared by all org repos). `quality`, `notify`, and `deploy` jobs all use `runs-on: [self-hosted, Linux, X64, docker]`.

**Runner infra (as of 2026-11-20, after the server move):** each runner is a Docker container (`myoung34/github-runner:ubuntu-noble`) started by one `docker-compose.yml` on the runner server (`~/actions-runner`). Settings: `RUNNER_SCOPE=org`, `ORG_NAME=aivance-kr`, `RUNNER_NAME=runner-01|02|03`, `LABELS=docker,linux` (runner-01 also `gpu`), `ACCESS_TOKEN` = PAT of an org Owner (classic, `admin:org`), `/var/run/docker.sock` mounted. The image adds `self-hosted`/`Linux`/`X64` itself. Check status: `gh api orgs/aivance-kr/actions/runners`.

> ⚠️ **Runner containers must use `network_mode: host`.** After the server move, containers on the compose network could not resolve `api.github.com` (`curl: (6) Could not resolve host`; with `dns: 8.8.8.8` forced, it timed out). The image then fails with a misleading `Invalid configuration provided for token` — the token was fine, DNS was the cause. If a runner stays `offline`, read `docker compose logs runner-1` and look for the `curl: (6)` line just above that message before touching the token.

- The server's Docker is the **snap** build. AppArmor (`docker-default`) can deny dockerd's kill signal, so `docker compose up --force-recreate` fails with `cannot stop container: permission denied`. Recover with `sudo snap restart docker`, then `docker ps -aq --filter name=gh-runner | xargs -r docker rm -f` and `docker compose up -d` (a failed recreate leaves `<hash>_gh-runner-N` containers that cause name conflicts). Replacing snap Docker with apt `docker-ce` is the lasting fix.

- **The repo is Public** — since a `pull_request` trigger on a self-hosted runner risks running external fork PR code on that runner (a well-known risk), the repo setting `fork-pr-contributor-approval` is set to `all_external_contributors`. External contributors' PRs don't run workflows until an admin manually approves them.
- **MySQL**: runner is itself a container, so host-published ports (`-p`, `services: ports`) are unreachable from the job. Each job creates a per-run network (`ci-net-<run_id>`), attaches the runner (`$(hostname)`) and the `docker run` MySQL container to it, connects by container name on port `3306`, and cleans up in an `if: always()` step. Redis isn't needed in CI since the cache handler defaults to `file`.
- **No fixed host ports**: because each run has its own network, concurrent jobs and other repos' jobs can't collide on ports (the old `43306`/`43307` scheme is gone). Still check the container is alive and ready right after startup and abort with `docker logs` on failure — `docker run -d` prints an ID even when the container dies at once.
- **Always clean up containers with `docker rm -f -v`.** Without `-v`, only the container is removed and the `/var/lib/mysql` anonymous volume remains. These pile up by hundreds of MB per run, filling the runner's Docker disk, until one day MySQL can't even start with `No space left on device`. This actually happened — 210 volumes (44GB) accumulated over a month starting 2026-07-17, blocking PR #255's deploy CI. Recovery when blocked: `docker volume prune -f` (doesn't touch volumes in use).
- **Several runners, jobs may run concurrently.** `quality`/`coverage` have no `needs` between them, so they can land on different runners at the same time; per-run container/network names (`<run_id>`) keep them isolated. Runner images can differ per runner, so a job must not assume PHP or extensions are preinstalled.
- **To revert to a hosted runner**: just change `runs-on` to `ubuntu-latest` (it's Linux, so MySQL can optionally switch to a `services:` block and the standard `3306`).

**Cron (production — single line registration):**
```
* * * * * cd /path/to/app && php spark tasks:run >> /dev/null 2>&1
```
`Config/Tasks.php` reads enabled jobs from the `settings` table and registers them. Manage enablement/schedule at `/admin/schedule`.

## Initial setup

```bash
cp env .env
# Edit .env: DB connection info, CI_ENVIRONMENT, TinyMCE key, app.baseURL (leave the local default of 8306
#            as-is, but if you run on a different port or deploy, always set the real URL — CI4 throws
#            an exception instead of auto-detecting if baseURL is missing or empty)
php spark migrate
# app/Config/App.php: set appTimezone = 'Asia/Seoul'
```

Default admin account: `admin@example.com` / `admin1234!`

Linux upload permissions: `chmod -R 755 public/uploads writable`

**Enable Git hooks (once after cloning):**
```bash
git config core.hooksPath .githooks
```
- `.githooks/pre-commit` — auto-applies PHP-CS-Fixer (`composer cs:fix` rules) to staged PHP files right before commit (doesn't block the commit).
- `.githooks/pre-push` — policy differs by target branch:
  - Direct push to `main` is **unconditionally blocked** (deploy only via `dev → main` PR).
  - Other branches run the quality gate (`composer ci` = cs, analyse, test, ~10 sec) to catch failures locally before a CI round-trip.
  - Docs-only changes (pushes touching only `*.md`, `docs/**`, `.claude/rules/**`) automatically skip verification. If even one line of code is mixed in, full verification runs immediately.
- Emergency bypass: `SKIP_HOOKS=1 git commit/push ...` (doesn't bypass the `main` block). In environments without PHP/Composer, that verification is automatically skipped.

## Detailed rules (modules)

- **Architecture** (theme system, BaseController, auth/routing, CSRF exceptions, caching, OAuth, file uploads, DB schema): @.claude/rules/architecture.md
