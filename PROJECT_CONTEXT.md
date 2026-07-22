# Project Context

Read this file first in any new chat — it's the fast path to full context on this codebase.
`CLAUDE.md` (Laravel Boost guidelines + audit-logging rules) covers *how to write code here*;
this file covers *what the application actually is*.

## What this is

A Laravel 13 **admin panel** (BLAT stack: Blade, Livewire 4, Alpine.js v3, Tailwind v4) for
managing three kinds of accounts that all live in a single `users` table: **app users**
(the real product's end users), **guests** (anonymous/unconverted accounts), and **staff**
(admin-panel operators with RBAC roles). There is currently no public-facing product UI in
this repo — just the admin panel itself (`/admin/*`) plus login/logout.

## Stack & versions

- PHP 8.4, Laravel 13, Livewire 4, Tailwind v4, Alpine.js v3
- `anousss007/blatui` — shadcn/ui-style copy-paste Blade components in `resources/views/components/ui/` (see BlatUI section in `CLAUDE.md`)
- `spatie/laravel-permission` — RBAC for staff (roles/permissions), guard `web`
- `spatie/laravel-activitylog` — audit trail (see "Activity logging" below)
- `spatie/laravel-sluggable` — `User::slug` generated from `name`
- `league/flysystem-aws-s3-v3` — powers a Cloudflare R2 disk (`r2` in `config/filesystems.php`, S3-compatible) for avatars/exports
- `mallardduck/blade-lucide-icons` — icon set used throughout (`<x-lucide-*>`)
- Laravel Boost (MCP dev-tooling), Pint, PHPUnit 12

Dev loop: `composer run dev` runs server + queue listener + pail (log viewer) + vite concurrently.
Served via Laravel Herd at `https://blatui.test` — never start `php artisan serve` manually.

## The User model — three types, one table

`app/Models/User.php`. A `type` column (`App\Enum\UserType`: `App` | `Staff` | `Guest`) distinguishes
the three kinds; query scopes `appUsers()` / `staff()` / `guests()` filter by it. Roles (Spatie
`HasRoles`) are **only ever attached to staff** — app users and guests are never given roles.

Key state, all first-class on the model:
- **Ban**: `banned_at` / `ban_reason` — `isBanned()`, scopes `banned()` / `notBanned()`.
- **Grace-period deletion** (app users only): `deletion_requested_at` / `deletion_requested_by`
  (`'user'` or `'admin'`) / `deletion_reason` — `isPendingDeletion()`, `deletionPurgesAt()`,
  `canCancelDeletion()` (only self-cancel if the user themselves requested it).
- **Lifecycle state**: `lifecycleState()` returns `'trashed'` (soft-deleted) | `'pending'`
  (deletion requested) | `'active'`. Every mutating action in the row-action traits re-checks
  this server-side via `assertLifecycleState()` — defense-in-depth behind whatever the UI hides.
- **Soft deletes** on all types; guests/app users additionally support a permanent, immediate
  purge (see Services below).
- `external_id` (ULID) is generated in `booted()` on create — a stable public identifier
  separate from the incrementing `id`.
- **Never put Spatie's `LogsActivity` trait on `User`** — bulk `whereIn()->update()` bypasses
  Eloquent events, and guest signups / `last_login` writes would flood the audit log. All
  activity logging is explicit, via `ActivityLogger` (see below).

## Authorization: permissions, not roles, everywhere

`config/panel.php` is the single source of truth for the whole RBAC surface:
- `modules` — each entry (`users`, `guests`, `staff`, `roles`, `activity_logs`, `dashboard`, `settings`)
  declares its allowed `actions` from a fixed `action_vocabulary` (`view`, `create`, `edit`,
  `delete`, `restore`, `force-delete`, `ban`, `unban`, `export`, `import`, `manage`, `access`,
  `reply`, `assign`, `convert`, `merge`), plus optional `children` sub-modules (`general`, `mail`, `policies`, `storage`, `authentication`). Permissions are generated as `{module}.{action}` or `{module}.{child}.{action}`.
- `User::canAccessModule($module)` inspects whether a user has `{module}.view` or any child `{module}.{child}.view` permission, powering module navigation.
- `super_admin_role` (`'super-admin'`) bypasses every check via `Gate::before` in
  `AppServiceProvider::configureSuperAdmin()` — which also handles module view permission inheritance (`{module}.view` grants view rights to all `{module}.{child}.view` permissions without granting edit rights).
- `protected_roles` (`super-admin`, `admin`) and `protected_permissions`
  (`panel.access-admin`) can't be deleted/renamed from the panel UI.
- `access` maps a panel identifier to its gating permission (`admin` → `panel.access-admin`).
- `features` — plan/subscription feature flags (`device_limit`, `ad_free`) with names, types, and defaults.
- `admin_excluded_permissions` — permissions the seeded `admin` role does *not* get (destructive
  system-level actions reserved for super-admin).
- `RolesAndPermissionsSeeder` is **idempotent and safe to re-run in production**: it
  `firstOrCreate`s permissions from config, deletes only permissions no longer in config (never
  protected ones), and re-syncs the `admin` role's permission set every run.

Route-level: every `admin.*` route group is behind `permission:{module}.{action}` middleware
(see `routes/admin.php`); `EnsurePanelAccess` middleware (aliased `panel`) additionally requires
`isStaff()`, `can('panel.access-admin')`, and not-banned. Livewire actions **also** call
`$this->authorize(...)` inline — route middleware alone doesn't cover component methods invoked
via Livewire's wire protocol.

`AuthenticateSession` middleware is stacked on the whole `admin.*` group — it's what makes
"log out other devices" on the account page work (session gets invalidated when the stamped
password hash no longer matches after `Auth::logoutOtherDevices()`).

## Livewire architecture

Three abstract bases in `app/Livewire/Admin/`, each composing focused traits from
`app/Livewire/Admin/Concerns/`. None of them pick a view or layout — every concrete page writes
its own `render()` + `#[Layout(...)]`.

- **`BaseIndex`** (list pages) — composes `HasFilters`, `HasBulkActions`, `HasStats`, `HasToast`,
  `WithPagination`. Subclasses implement `baseQuery()`; `getRecords()` chains search → filters →
  sort → paginate. `sort()` toggles direction on repeat clicks.
  - `HasFilters` — generic `$search` + `$filters` array driven by a `filterConfig()` map of
    `apply` closures (query-building) plus a separate `filterBarConfig()` (UI-only, no closures,
    for the Blade filter-bar component).
  - `HasBulkActions` — `$selectedIds` (cross-page), a `bulkActionConfig()` registry (key, label,
    icon, `confirm`, `variant`, `permission`, optional `when` closure, optional `dialog_event`),
    and selection helpers (`toggleSelection`, `selectAllOnPage`, etc.).
  - `HasStats` — `statsConfig()` → `resolveStats()` resolves each stat's `value` closure lazily
    for the stat-card row under the page header.
- **`BaseForm`** (create/edit) — composes `HasToast`. Subclasses implement `indexRoute()`;
  `redirectWithSuccess()` flashes a toast and redirects there.
- **`BaseShow`** (read-focused detail/profile page) — composes `HasToast`. Subclasses call
  `initShow($record)` from their own typed `mount()` (keeps route-model binding on the child),
  implement `indexRoute()` + `title()`, optionally override `viewPermission()`. Derives
  breadcrumbs from the index route name via `Str::headline`.
  - **`HasShowTabs`** (opt-in) — a tab *registry* (`tabs()`: key → label/icon/view
    Blade-partial/optional `permission`/optional `data` closure), `#[Url]`-bound active tab.
    The page renders purely from the registry — **no `if ($tab === '...')` branching** anywhere;
    adding a tab is a one-line registry entry. Unbuilt tabs render a shared
    `.../profile/tabs/placeholder.blade.php` "coming soon" view rather than being hidden.

**Row-action traits** (e.g. `Management/Users/Concerns/HandlesUserRowActions`,
`Management/Guests/Concerns/HandlesGuestRowActions`) hold the actual mutating Livewire actions
(ban, delete, restore, force-delete, convert, merge, schedule/cancel/instant-purge deletion) and
are used by **both** the Index and the Show page for a module — this is deliberate: a ban from
the profile header must run byte-for-byte the same code (and write the same audit row) as a ban
from the index row. They require the using component to also use `LogsAdminActivity` + `HasToast`.

`HasToast::toast()`/`toastSuccess()`/`toastError()`/`toastWarning()`/`toastInfo()` dispatch a
`toast` browser event picked up by `<x-ui.sonner>` in the admin layout. A same-request redirect
instead flashes `session('toast')`, which the layout turns into a `toast` event on page load
(see the inline script in `layouts/admin/app.blade.php`).

## Account lifecycle services (`app/Services/`)

All destructive/state-transition logic for accounts lives here — **never duplicate deletion,
conversion, or merge logic inline in a Livewire component.**

- **`AccountDeletionService`** — the only place account deletion happens, for both app users
  and guests, from both the admin panel and the scheduled sweep.
  - App users: two-phase — `requestByUser()`/`requestByAdmin()` marks the account (stays live for
    `panel.account_deletion_grace_hours`, default 24h via `ACCOUNT_DELETION_GRACE_HOURS` env);
    `cancelByUser()` (only if user-initiated) / `cancelByAdmin()` (any) clears it;
    `purgeExpired()` (called hourly by `PurgeExpiredAccounts` job) permanently removes any account
    past its grace period; `instantPurgeByAdmin()` skips the grace period entirely.
  - Guests: `purgeGuestByAdmin()` skips straight to `purge()` — no request/cancel phase at all.
  - `purge()` is transactional and idempotent (`if (! $user->exists) return;`), snapshots the
    account into the audit-log properties before `forceDelete()`, and cleans up related rows
    (`deleteRelatedData()` — currently guarded no-ops for `subscriptions`/`devices` tables that
    don't exist yet; sessions and personal-access-tokens *are* cleaned).
  - Scheduled purges use an explicit `causer: null` + `context: Scheduler` (no `auth()` session
    exists in that context); admin-triggered purges auto-resolve the causer/context.
- **`GuestConversionService`** — flips a guest in place into an app user (same row/id).
  `convertBySelf()` (user sets their own email+password), `convertByAdmin()` (admin sets email;
  password is a random unusable string — admin never sets/sees a real password; a reset-link send
  is TODO-deferred), `convertWithGoogle()`/`convertWithApple()` (OAuth — auto-merges into an
  existing app account if one already matches on provider-id or email). Also exposes
  `mergeByAdmin()` which delegates to `AccountMergeService`.
- **`AccountMergeService`** — merges a guest's identity into an *existing* app account; the
  destination survives, the guest row is `forceDelete()`d. `mergeFromProvider()` (self-service,
  OAuth-driven, no reason needed) vs `mergeByAdmin()` (requires a non-empty `$reason` — no
  provider proof, so it must be traceable as an admin judgment call). `migrateRelatedData()` is a
  TODO stub for once `devices`/`subscriptions` tables exist.

These three services are why **the current uncommitted work** (per git status) exists: guest
conversion/merge functionality was just added — `AccountMergeService` is new, and
`GuestConversionServiceTest` / `GuestShowTest` / the guest dialogs were updated alongside it.

## Activity logging

Fully documented in `CLAUDE.md` under "Audit Logging" — read that section for the mechanics.
Quick orientation: everything funnels through `App\Support\ActivityLogger::log()` (or the
`LogsAdminActivity` trait's `logActivity()`/`auditDiff()` convenience wrappers from Livewire).
Four orthogonal axes, all enums in `app/Enum/`: `ActivityLogName` (category — Audit/Authentication/
System), `ActivityModule` (feature — User/Guest/Staff/Role/Permission/Plan/Server/Ticket),
`ActivityAction` (reusable verb — Created/Updated/Deleted/Banned/Converted/Merged/…),
`ActivityContext` (originating runtime — Admin/Api/Scheduler/Queue/Console/Webhook, mostly
auto-detected). The viewer + CSV export share one query builder, `App\Support\ActivityLogQuery`,
built from a serializable filter-state array — so `App\Jobs\ExportActivityLog` (queued CSV export
for exports past `panel.activity_log_export_queue_threshold`, default 5000 rows) always matches
what the admin was looking at on screen. `App\Listeners\AuthActivityListener` auto-logs
login/failed-login (rate-limited)/password-reset via Laravel's event discovery — guests are never
logged for auth or account activity.

## Plans & Subscriptions data model

Data layer only so far — **no admin UI, no seeder, no service wiring yet** (see rough edges
below). Five new tables/models under `app/Models/`:
- **`Plan`** (`#[Sluggable(from: 'name', to: 'slug')]`, soft-deletable) — `name`, `slug`,
  `description`, `features` (`array` cast), `is_active`, `is_best_deal`, `sort_order`. Has many
  `prices()` (`PlanPrice`) and `subscriptions()`.
- **`PlanPrice`** (soft-deletable) — belongs to `Plan`; `amount`/`compare_at_amount` (`decimal:2`),
  `currency`, `billing_period`+`billing_interval`, `trial_period`+`trial_interval`,
  `grace_period`+`grace_interval` (the three `*_interval` columns all cast to
  `App\Enum\BillingInterval`). Has many `providers()` (`PlanPriceProvider`) and `subscriptions()`.
- **`PlanPriceProvider`** — belongs to `PlanPrice` via `planPrice()`; maps a price to an external
  provider price/product id (`provider` cast to `App\Enum\PaymentProvider`, `external_id`). Unique
  on `(plan_price_id, provider)`.
- **`Subscription`** — belongs to `User`, `Plan`, `PlanPrice` (`planPrice()`), and optionally a
  `previousSubscription()` (self-referencing, for upgrade/downgrade chains). `status` casts to
  `App\Enum\SubscriptionStatus`, `cancelled_by` to `App\Enum\CancelledBy`, `provider` to
  `PaymentProvider`. `isActive(): bool` checks status is one of Trialing/Active/Grace and
  `ends_at` hasn't passed. Has many `receipts()`.
- **`SubscriptionReceipt`** — belongs to `Subscription`; one row per provider webhook/event
  (`type` cast to `App\Enum\ReceiptType`: Initial/Renewal/Restore/Refund/Cancellation).

`User::subscriptions(): HasMany<Subscription>` added alongside `policyAcceptances()`
(`app/Models/User.php`). All five models + factories exist; migrations are
`2026_07_22_000000_create_plans_tables.php` and `..._000001_create_subscriptions_tables.php`.
Per this codebase's hard convention, none of the "enum-like" columns above are native DB
`enum()` columns — they're all `string` + a PHP backed-enum cast.

## Routes

- `routes/web.php` requires `auth.php` then `admin.php`.
- `routes/auth.php` — `GET /login` (guest-only), `GET /logout`; plus two self-service pages:
  `GET /verify-email/{id}/{hash}` (`verification.verify`, `auth+signed+throttle:6,1` — staff clicking
  the emailed link verifies in place) and `GET /reset-password/{token}` (`password.reset`,
  guest-only — sets a new password given a valid broker token; there's no "request a link" page,
  a reset link is only ever sent by the system, e.g. via `Password::sendResetLink()`). These are
  the exact route names `App\Services\Auth\UrlResolver` looks for when building notification URLs,
  and satisfy the preconditions the `GuestConversionService` TODOs were waiting on (see rough edges
  below).
- **`App\Services\Auth\UrlResolver`** builds both URLs and auto-detects panel vs frontend from
  `panel.auth_url_mode` (`env('AUTH_URL_MODE')`, default `'auto'`): `'panel'`/`'frontend'` force a
  side, `'auto'` sends staff to the panel and everyone else to the frontend
  (`panel.frontend_url` / `env('FRONTEND_URL')`, falls back to `APP_URL`). Verification always goes
  through a real `URL::temporarySignedRoute()` — never a hand-rolled HMAC — against
  `verification.verify` (panel) or `api.verification.verify` (frontend/API, once that route exists;
  falls back to the panel route via `Route::has()` until then, since the API surface isn't built
  yet). The frontend/API signed route is generated against `panel.frontend_url` rather than the
  panel's own `APP_URL` — `URL::temporarySignedRoute()` alone always signs against the app's own
  root, so `UrlResolver::signedRouteOn()` temporarily overrides both host and scheme via
  `URL::useOrigin()` + `URL::forceScheme()` for that one call (restored in a `finally` block) —
  otherwise a `quixure.com` link would get built as if it lived on `panel.quixure.com`. Password
  reset needs no signed route (the broker token is already the credential); the
  frontend variant is a plain link to `{frontend_url}/reset-password?...` for an SPA page to render.
  In both cases the `email` query param is `Crypt::encryptString()`d rather than plain-text — the
  panel's `PasswordReset` Livewire component decrypts it in `mount()`, and any future API endpoint
  must do the same. Neither notification (`VerifyEmailNotification`/`ResetPasswordNotification`)
  makes the panel-vs-frontend choice itself — that logic lives solely in `UrlResolver`.
- `routes/admin.php` — everything under `auth + panel + AuthenticateSession` middleware, name
  prefix `admin.`: `dashboard`, `users.*` (index/create/edit/show, `withTrashed()` on show),
  `guests.*` (index/show only — no create/edit, guests aren't created via the panel), `staff.*`
  (index/create/edit — no show page, no delete route defined yet), `roles.*` (index/create/edit),
  `activity-logs.*` (index only, read-only), and a single `account` route (self-service "My
  Account" page — no extra permission, every staff member can reach it).
- Each route group carries its own `permission:{module}.{action}` middleware layered on top of the
  group-level `permission:{module}.view`.

## Scheduled/queued work (`routes/console.php`)

- `PurgeExpiredAccounts` job — hourly, `withoutOverlapping()`, 1 retry (idempotent purge, next
  hourly run catches stragglers on failure), 300s timeout.
- `activitylog:clean` Artisan command (Spatie's built-in pruning) — weekly.

## Directory map

```
app/
  Enum/            UserType, Activity{LogName,Module,Action,Context}, MailPurpose,
                   BillingInterval, PaymentProvider, SubscriptionStatus, CancelledBy, ReceiptType
  Http/Middleware/ EnsurePanelAccess (alias: panel)
  Jobs/            PurgeExpiredAccounts, ExportActivityLog
  Listeners/       AuthActivityListener
  Livewire/
    Auth/          Login, Logout, VerifyEmail, PasswordReset (reset-with-token form only)
    Admin/         BaseIndex, BaseForm, BaseShow + Concerns/ (shared traits)
      Dashboard.php
      Account/Index.php                     self-service account page
      Management/Users/                     Index, Show, Form + Concerns/HandlesUserRowActions
      Management/Guests/                    Index, Show + Concerns/HandlesGuestRowActions
      Administration/Staff/                         Index, Form (staff CRUD + role assignment)
      Administration/Roles/                         Index, Form (role/permission-matrix CRUD)
      Administration/ActivityLogs/Index.php         read-only audit viewer
      Settings/                             BaseSettings, Index, General, Mail, Policies
  Mail/            Concerns/HasMailPurpose.php (trait for purpose-based mailables),
                   Auth/VerifyEmailMail.php, Auth/ResetPasswordMail.php
  Models/          User.php (canAccessModule helper), EmailDomain.php, EmailSender.php, SmtpSetting.php, Policy.php, PolicyVersion.php, PolicyAcceptance.php,
                   Plan.php, PlanPrice.php, PlanPriceProvider.php, Subscription.php, SubscriptionReceipt.php
  Notifications/   Auth/VerifyEmailNotification.php, Auth/ResetPasswordNotification.php
  Providers/AppServiceProvider.php          CarbonImmutable default, super-admin Gate::before,
                                             module view permission inheritance policy
  Services/        AccountDeletionService, AccountMergeService, GuestConversionService, MailConfigurator, Auth/UrlResolver
  Support/         ActivityLogger, ActivityLogQuery
config/panel.php    RBAC modules/actions/children, grace period, export threshold, seeded admin creds
database/
  migrations/       users, permission_tables (Spatie), activity_log (+ 3 hand-added indexes),
                     cache, jobs, email_domains, email_senders, smtp_settings, policies_tables,
                     plans_tables (plans/plan_prices/plan_price_providers),
                     subscriptions_tables (subscriptions/subscription_receipts)
  seeders/          DatabaseSeeder, RolesAndPermissionsSeeder (idempotent), UserSeeder,
                     EmailSendersSeeder (idempotent)
  factories/         one per model, incl. Plan/PlanPrice/PlanPriceProvider/Subscription/SubscriptionReceipt
resources/
  views/components/ui/       BlatUI copy-paste components (x-ui.*) — see CLAUDE.md BlatUI section
  views/components/admin/    panel-specific composites: filter-bar, page-header, pagination,
                              confirm-dialog, reason-dialog, show-tabs, stat-card, dropdown, tooltip
  views/layouts/admin/       app.blade.php (sidebar shell), guest.blade.php (login)
  views/livewire/admin/      one folder per Livewire component, mirroring app/Livewire/Admin
  css/blatui.css             design tokens (CSS vars on :root/.dark/[data-*])
tests/Feature/       AccountDeletionTest, PurgeExpiredAccountsTest, ActivityLogTest,
                     ActivityLogsViewerTest, UserShowTest, GuestsIndexTest, GuestShowTest,
                     GuestConversionServiceTest, EmailSenderResolutionTest, SettingsMailTest,
                     MailConfiguratorTest, UrlResolverTest, VerifyEmailTest, PasswordResetTest
```

## Known rough edges / deferred work (don't be surprised by these)

- `UserSeeder` assigns `config('panel.app_user_role')` to the local test user, but `panel.php`
  only defines `super_admin_role` — app users/guests are distinguished by `type`, not roles, so
  this key doesn't exist. Local-only seeding path; harmless but dead config lookup.
  - Note: app users are never assigned Spatie roles anywhere else in the codebase — this line
    looks like leftover/aspirational code.
- Several TODOs mark intentionally-deferred wiring: email verification notifications on guest
  conversion, password-reset-link dispatch on admin-initiated conversion, and
  `AccountMergeService::migrateRelatedData()` / `AccountDeletionService::deleteRelatedData()` for
  `subscriptions`/`devices` tables. The `subscriptions` table now exists (see "Plans &
  Subscriptions data model" above), but these two methods haven't been revisited yet — the
  deletion-service cleanup still works (it's a guarded `Schema::hasTable()` check that now passes
  for `subscriptions` but still no-ops for `devices`), while the merge-service reassignment is
  still an unimplemented stub either way.
- `Users/Show.php` has three scaffolded-but-inert actions (`verifyEmailManually`,
  `resendVerificationEmail`, `sendPasswordResetLink`) that toast "not yet available" rather than
  perform the action — wiring points for when Laravel's real verification/password-broker flows
  are integrated.
- Staff module has no `show` route/page and no `delete` route — staff are only listed/created/edited.
- Mail-sending data layer (`EmailDomain`, `EmailSender`, `SmtpSetting` models; `MailPurpose` enum —
  `Default`/`Auth`/`Notifications`/`Support`/`Billing`/`Marketing`; `EmailSendersSeeder` seeds one
  `EmailSender` row per purpose) now has a real admin page: `admin.settings.mail`
  (`App\Livewire\Admin\Settings\Mail`, part of the `Settings/{BaseSettings,Index,General,Mail,Policies}`
  hub — a tabbed settings shell with its own `#[Layout('livewire.admin.settings.index')]`). The page reads
  `config('mail.default')` (i.e. `MAIL_MAILER` from `.env`, still not admin-editable) and shows either the
  SMTP form or the Resend domains/purposes manager accordingly. `General` is still a stub
  (`saveSettings()` no-ops) — `Mail` and `Policies` (with versioning and user acceptance helpers) are wired to real persistence.
  - `config('mail.default')` is still **not** consulted by Laravel's actual mail transport — no
    `AppServiceProvider` runtime override exists yet, so `EmailSender::resolve(MailPurpose)` /
    `SmtpSetting::current()` are populated and admin-editable but nothing reads them when mail is
    actually sent. That's the next piece if/when real Mailables are added.
  - Fixing this page also required adding a `'view'` base action to the `settings` module in
    `config/panel.php` (generating `settings.view`) — the pre-existing `routes/admin.php` Settings group
    required it at the group level but it had never been declared, which would have 403'd every non-super-admin
    regardless of their `settings.mail.*` grants.

## Related memory files

See `[[project_stack]]`, `[[livewire-blatui-gotchas]]` (teleport dropdowns & native checkboxes
break under Livewire morphing — use `x-admin.dropdown` + Alpine `:checked` binding), and
`[[carbon-immutable-dates]]` (app-wide `CarbonImmutable` via `Date::use()` — type date helpers as
`CarbonInterface`, not `Illuminate\Support\Carbon`) in the assistant's persistent memory for
cross-session gotchas not captured in code comments.
