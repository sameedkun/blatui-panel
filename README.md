# Admin Panel

[![Tests](https://github.com/sameedkun/blatui-panel/actions/workflows/tests.yml/badge.svg)](https://github.com/sameedkun/blatui-panel/actions/workflows/tests.yml)

A Laravel admin panel for managing accounts, subscriptions, support tickets, and platform
operations — built to be a solid, reusable starting point rather than a single-purpose app.
It ships with permission-based RBAC, audit logging, device/session management, IP blocking,
billing/subscription plumbing, a support-ticket system, and a tabbed analytics dashboard, all
wired together so a new project can drop in its own product-specific modules on top.

> New to this codebase? Read [`PROJECT_CONTEXT.md`](PROJECT_CONTEXT.md) first — it's the fast
> path to understanding what's actually built here. [`CLAUDE.md`](CLAUDE.md) documents the
> coding conventions (audit logging, testing, code style) this project follows.

## Stack

- **PHP 8.4**, **Laravel 13**
- **Livewire 4** + **Alpine.js v3** + **Tailwind CSS v4**
- [`anousss007/blatui`](https://blatui.remix-it.com) — shadcn/ui-style, copy-paste Blade
  components (owned in `resources/views/components/ui/`, not a runtime dependency)
- `spatie/laravel-permission` (RBAC), `spatie/laravel-activitylog` (audit trail),
  `spatie/laravel-passkeys` (WebAuthn login), `laravel/sanctum` (API auth),
  `laravel/socialite` (Google/Apple sign-in)
- `grazulex/laravel-apiroute` for versioned REST API routes, `dedoc/scramble` for
  auto-generated OpenAPI docs, `opcodesio/log-viewer` for in-app log browsing

## What's included

- **Three account types, one table** — app users, guests, and staff, with ban/soft-delete/
  grace-period-deletion lifecycles and full audit trails.
- **Permission-driven RBAC** — every module, action, and route is gated by a generated
  `{module}.{action}` permission (see `config/panel.php`), not by role name checks.
- **Plans, subscriptions & billing lifecycle** — plan/price management, subscription
  assignment/upgrade/cancellation, and a scheduled job that drives trial → active → grace →
  expired transitions.
- **Support tickets** — categories with agent pools, load-balanced auto-assignment, file
  attachments, and inactivity/auto-close sweeps.
- **Device management & IP blocking** — per-device session tracking, device limits, and
  global/per-user IP blocks enforced at the middleware layer.
- **Tabbed analytics dashboard** — audience, revenue, support, security, and system metrics,
  each gated by permission and loaded lazily per tab.
- **Versioned REST API** (`/api/v1/...`) — signup/login, self-service profile, devices,
  subscriptions, tickets, feedback, and a public content catalog, with auto-generated OpenAPI
  docs.
- **Request logging & analytics** — every API request sampled, sanitized, and rolled up into
  hourly/daily/monthly stats with its own admin viewer.

## Requirements

- PHP 8.4+ with the extensions Laravel 13 expects (`mbstring`, `pdo`, `bcmath`, `intl`, `gd`, `zip`, ...)
- Composer 2
- Node.js 22+ and npm
- MySQL (or another Laravel-supported database) for local development — the test suite runs
  against an in-memory SQLite database, so no database server is required just to run tests
- Redis (used for caching, queues, and the API request-log buffer in production-like setups)

## Getting started

Clone the repo, then either use the one-shot setup script or the manual steps below.

```bash
composer run setup
```

This copies `.env.example` to `.env`, generates an app key, runs migrations, and builds
frontend assets. Or, step by step:

```bash
composer install
cp .env.example .env
php artisan key:generate

# configure DB_* and other services in .env, then:
php artisan migrate

npm install
npm run build
```

Seed roles/permissions and a starter admin account:

```bash
php artisan db:seed
```

### Running the app

```bash
composer run dev
```

This starts the PHP dev server, a queue listener, `pail` (log tailing), and Vite concurrently.
If you're using [Laravel Herd](https://herd.laravel.com), the site is already served at your
configured `.test` domain and you don't need `php artisan serve` — just run `npm run dev` (or
`composer run dev` minus the server process) for asset watching.

## Testing

The full suite runs against an in-memory SQLite database with array/sync drivers, so it needs
no external services:

```bash
composer test
# or, to run a single file / filter:
php artisan test --compact tests/Feature/Admin/Accounts/Users/UserFormTest.php
php artisan test --compact --filter=testName
```

Code style is enforced with [Laravel Pint](https://laravel.com/docs/pint):

```bash
vendor/bin/pint
```

Static analysis is enforced with [Larastan](https://github.com/larastan/larastan) (PHPStan,
level 5). Pre-existing findings are tracked in `phpstan-baseline.neon` so CI only fails on
*new* issues — run it locally with:

```bash
composer analyse
```

When you fix a baselined issue, regenerate the baseline so it isn't silently reintroduced:

```bash
vendor/bin/phpstan analyse --generate-baseline
```

### Continuous integration

Every pull request runs the full test suite, a Pint style check, and Larastan static analysis
automatically via GitHub Actions — see
[`.github/workflows/tests.yml`](.github/workflows/tests.yml). This keeps local runs fast for
day-to-day development while still catching regressions before merge; you don't need to run
the entire suite locally before opening a PR.

## Agentic development

This project ships [Laravel Boost](https://laravel.com/docs/ai) plus project-specific rules in
[`CLAUDE.md`](CLAUDE.md), [`PROJECT_CONTEXT.md`](PROJECT_CONTEXT.md), and `.ai/rules/`, so AI
coding agents (Claude Code, Cursor, GitHub Copilot, etc.) have accurate, up-to-date context on
this codebase's architecture and conventions out of the box.

## Learning Laravel

Laravel has extensive [documentation](https://laravel.com/docs) and a large ecosystem of
learning resources — see [laravel.com/docs](https://laravel.com/docs) and
[Laracasts](https://laracasts.com) if you're new to the framework itself.
