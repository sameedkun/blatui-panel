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
- `modules` — each entry (`users`, `guests`, `plans`, `subscriptions`, `tickets`, `ticket_categories`, `staff`, `roles`, `activity_logs`, `dashboard`, `settings`)
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

## Plans & Subscriptions

Five tables/models under `app/Models/`, a full admin UI, and full user-facing subscription
management from the Users profile page. `Plan`/`PlanPrice` are **not** soft-deletable (plain
`Model`) — a plan/price is either hard-deleted (blocked while it has any subscription) or retired
via `is_active = false`; `Subscription` rows themselves are permanent records, never deleted.
- **`Plan`** (`#[Sluggable(from: 'name', to: 'slug')]`, `HasFeatures` trait reading
  `config('panel.features')`) — `name`, `slug`, `description`, `features` (`array` cast),
  `is_active`, `is_best_deal`, `sort_order`. Has many `prices()` (`PlanPrice`) and `subscriptions()`.
- **`PlanPrice`** — belongs to `Plan`; `amount`/`compare_at_amount` (`decimal:2`), `currency`,
  `billing_period`+`billing_interval`, `trial_period`+`trial_interval`, `grace_period`+
  `grace_interval` (the three `*_interval` columns cast to `App\Enum\BillingInterval`).
  `billingDurationInDays()`/`trialEndsAt()`/`graceEndsAt()` compute the date math
  `App\Services\SubscriptionService` relies on. Has many `providers()` (`PlanPriceProvider`) and
  `subscriptions()`.
- **`PlanPriceProvider`** — belongs to `PlanPrice` via `planPrice()`; maps a price to an external
  provider price/product id (`provider` cast to `App\Enum\PaymentProvider`, `external_id`). Unique
  on `(plan_price_id, provider)`.
- **`Subscription`** — belongs to `User`, `Plan`, `PlanPrice` (`planPrice()`), and optionally a
  `previousSubscription()` (self-referencing, for upgrade/downgrade chains — `plan_id`/
  `plan_price_id` FKs are `restrictOnDelete()`, the DB-level backstop for the same rule the admin
  UI enforces). `status` casts to `App\Enum\SubscriptionStatus`, `cancelled_by` to
  `App\Enum\CancelledBy` (`User`/`Admin`/`System`), `provider` to `PaymentProvider`.
  `isActive(): bool` checks status is one of Trialing/Active/Grace and `ends_at` hasn't passed.
  Has many `receipts()`.
- **`SubscriptionReceipt`** — belongs to `Subscription`; one row per provider webhook/event
  (`type` cast to `App\Enum\ReceiptType`: Initial/Renewal/Restore/Refund/Cancellation). Not yet
  surfaced anywhere in the admin UI.

Per this codebase's hard convention, none of the "enum-like" columns above are native DB `enum()`
columns — they're all `string` + a PHP backed-enum cast.

### Admin UI (`app/Livewire/Admin/Management/Plans/`)

`Index`/`Form`/`Show`, routed at `admin.plans.*` (permissions `plans.view/create/edit/delete/
manage` — `.manage` gates the Show page, same split as Users/Guests). Single-row actions
(`toggleActive`, `confirmDelete`/`delete`) live in `Concerns/HandlesPlanRowActions`, shared by
Index and Show exactly like `HandlesUserRowActions`; `Show::delete()` overrides the trait's
version to redirect back to the index since deleting removes its own record. **A plan (or one of
its prices) can never be deleted while it has any subscription** — `hasSubscriptions()` guards
both the confirm step and the mutation itself (defense-in-depth); retiring one is `is_active =
false` instead. `Show` has tabs for Overview, Prices (with their provider mappings), Subscriptions
(every subscriber, filterable by status), and Activity.

### Subscriptions admin module (`app/Livewire/Admin/Management/Subscriptions/`)

A standalone `Index`/`Show` pair, routed at `admin.subscriptions.*` (module `subscriptions`,
actions `view`/`manage` only — no create/edit/delete, since `Subscription` rows are permanent
records created only via `SubscriptionService`). This is the cross-cutting view over every
subscription ever sold, independent of which plan or user it's for:
- `Index` — every subscription across every user/plan, with stats (Total, Active, Cancelled,
  Revenue Collected), status/plan/provider filters, and search across the `user`/`plan` relations
  (`whereHas`, since `Subscription` has no name/email column of its own — plain `searchableColumns`
  doesn't reach relations). No bulk actions (deliberate — these are audit records, not bulk-editable
  rows).
- `Show` — full detail for one subscription row: plan/price/provider info, the owning user, a
  Receipts tab (`SubscriptionReceipt` rows — first real surface for that model), and an Activity
  tab (see caveat below). Tabs and hero header link out to `admin.users.show`/`admin.plans.show`
  when the viewer holds `users.manage`/`plans.manage`.
- Cancel/reactivate actions live in `Concerns/HandlesSubscriptionRowActions`, shared by Index and
  Show exactly like `HandlesPlanRowActions` — both ultimately call `SubscriptionService`, keyed by
  *user*, not by the specific row. `isLive(Subscription)` (is this row the user's actual current
  subscription, i.e. does it match `$user->activeSubscription`?) gates every mutation and every
  action button, so a stale/historical row can never be cancelled by mistake — only the live row
  ever offers Cancel/Reactivate; anything else renders as a read-only "Historical record".
- Caveat: subscription events are logged with the **User** as subject (see `SubscriptionService`
  above), never the `Subscription` row itself, so the Show page's Activity tab can't use Spatie's
  `forSubject()` — it queries `Activity` directly on `subject_type=User` + `properties->type like
  'subscription_%'`, which surfaces the user's full subscription history rather than a precise
  per-row slice (the data model has no `subscription_id` on the activity properties to disambiguate
  further).
- The Users/Show subscription-history table, the Guests/Show subscriptions tab, and the Plans/Show
  subscriptions tab all link out to `admin.subscriptions.show` per row (gated on
  `subscriptions.manage`) — every subscription surface in the panel ties back to this one detail page.

### User-facing subscription management

`App\Traits\HasSubscriptions` (mixed into `User`) — `subscriptions()`, `activeSubscription()` (a
`hasOne` matching trialing/active-with-`ends_at` in the future, grace-with-`grace_ends_at` in the
future, or cancelled-but-`ends_at`-still-future — i.e. cancelling doesn't cut access off until the
period actually ends, unless done "immediately"), `isSubscribed()`, `isOnTrial()`, `isInGrace()`,
`currentPlan()`, `planFeature()`. `App\Traits\HasFeatures` (mixed into `Plan`) resolves a feature
key against `config('panel.features')`'s type/default.

**`App\Services\SubscriptionService`** is the only place subscription state changes — mirroring
`AccountDeletionService`, it logs its own audit rows (module `User`, subject the affected user) so
every caller (admin panel, future API) gets the trail for free:
- `subscribe(User, PlanPrice, provider='local')` — brand-new subscription; cancels any existing
  active one first (immediate, `cancelled_by: system`). Logs `Assigned` /
  `subscription_assigned`.
- `upgrade(User, PlanPrice, provider='local')` — replaces the current subscription, computing a
  proration credit off the remaining days and linking `previous_subscription_id` (preserves the
  chain, unlike `subscribe()`). Logs `Assigned` / `subscription_upgraded`.
- `cancelActive(User, cancelledBy, reason, immediately)` — `immediately=true` sets `ends_at =
  now()` (access cut off right away); `immediately=false` just flips `status` to `cancelled` and
  turns off `is_recurring`, leaving `ends_at` in the future so `activeSubscription()` still
  resolves it (access continues) until the period naturally ends. Logs `Cancelled` /
  `subscription_cancelled`.
- `reactivate(User)` — undoes a cancel while still cancelled-but-live (`ends_at` still future);
  throws if there's nothing eligible. Logs `Updated` / `subscription_reactivated`.

**`App\Services\SubscriptionLifecycleService`** is the counterpart for the *calendar-driven*
transitions — deliberately a separate class from `SubscriptionService` because it runs unattended
off the scheduler rather than in response to a user/admin action. `syncStatuses(array $providers =
[PaymentProvider::Local])` loops each provider and, per provider, moves every non-terminal
subscription **one step** when its next boundary date has passed (never straight past an
intermediate state even if several boundaries have since elapsed — a later run catches whatever's
still overdue): `trialing`→`active`/`expired` (trial ended; `is_recurring` decides which — `local`
has no real gateway to ask, so recurring is treated as "renewal succeeds"), `active`→`grace`
(period ended, recurring, price has a grace window) or straight to `expired` (not recurring, or no
grace available), `grace`→`expired` (grace window also elapsed), `cancelled`→`expired` (the
already-agreed access period ran out). Two transitions from the full state graph are deliberately
**not** handled here since they aren't calendar-driven: `active`→`cancelled` is always the explicit
`cancelActive()` action, and `grace`→`active` needs a real "payment received" signal `local` can't
produce from dates alone (a future provider integration or an admin "mark paid" action would supply
it). Only `local`-provider subscriptions are swept today — a real provider must confirm its own
renewal charges via webhook/reconciliation before this same transition set is safe to run against
it; adding one later is just appending to the `$providers` array passed to the job, no code change
needed here. Each transition category runs as a **single bulk `UPDATE ... WHERE id IN (...)`** per
chunk of up to 500 matching rows (snapshotting IDs first, since the update mutates the very
`status` column the scope filters on) rather than one query per subscription — the only per-row
work left is the (unavoidable) individual `ActivityLogger` entry per affected subscription, since
audit properties are subject-specific. One transition, `trialing`→`expired` (non-recurring), stays
fully per-row even for its update — its `proration_meta` JSON merge depends on each row's own
existing value, so there's no static payload a bulk statement could write across every row at once.
Logs with `causer: null` + `ActivityContext::Scheduler` (no `auth()` session in this context),
mirroring `AccountDeletionService::purge()`'s scheduled path. Types logged: `subscription_expired`
(with a `reason` property: `trial_lapsed`/`not_recurring`/`renewal_unconfirmed`/`grace_exhausted`/
`cancellation_ended`), `subscription_trial_converted`, `subscription_entered_grace` — all three
extend `ActivityPresenter` the same way the four `SubscriptionService`-logged types do.

`App\Jobs\SyncSubscriptionStatuses` is a thin scheduled trigger (mirrors `PurgeExpiredAccounts`):
constructor takes `array $providers = [PaymentProvider::Local]`, `handle()` just calls
`SubscriptionLifecycleService::syncStatuses($this->providers)`. Scheduled hourly in
`routes/console.php` as `new SyncSubscriptionStatuses([PaymentProvider::Local])`.

Admin panel surface: `Users/Show.php` (`app/Livewire/Admin/Management/Users/`) — an "Assign /
Change Plan" dialog (cascading Plan → active-prices-for-that-plan selects), "Cancel Immediately"
and "Cancel at Period End" reason-dialogs, and a one-click "Reactivate" — all gated by
`users.manage` (no new permission needed). The Overview tab shows a compact active-subscription
glance; the Subscriptions tab (replacing the old "coming soon" placeholder) has full management
plus the subscription history table. The Users index has a "Plan" column (plan name or "Free").
`ActivityPresenter` is module/type-aware for these (`properties.module === 'user'` +
`properties.type` starting with `subscription_`) so the Activity tab renders "Plan Assigned"/
"Plan Changed"/"Subscription Cancelled"/"Subscription Reactivated" rather than generic wording.

**`Guests/Show.php` has the identical surface**, gated on `guests.manage` instead — a guest is
still just a `type=Guest` row in the same `users` table, so `HasSubscriptions`/`SubscriptionService`
apply unmodified. Rather than duplicating the dialogs/partials, Guests/Show's `subscriptions` tab
registry entry points straight at the Users profile's `subscriptions` Blade partial (same pattern
already used for the Activity tab), and `show.blade.php` includes the same
`users/partials/subscription-dialogs` partial — so a guest's Assign/Cancel/Reactivate is
byte-for-byte the same Blade and the same `SubscriptionService` calls as a user's, just with
`guests.manage` as the gate instead of `users.manage`. Guests/Show's Overview tab got the same
"Active Subscription" glance card. `statCards()`'s `Plan` stat (name or "Free") replaced the old
`Subscriptions` "coming soon" placeholder there too.

## Support Tickets

`app/Models/{Ticket,TicketCategory,TicketMessage}.php`, backed by four tables from a single
migration (`categories`, `tickets`, `ticket_messages`, `category_agent`). `TicketCategory` maps to
the `categories` table via an explicit `protected $table` (named `TicketCategory`, not `Category`,
to stay unambiguous). Neither `Ticket` nor `TicketCategory` is soft-deletable in the usual sense —
`Ticket` has no soft deletes at all (permanent record, like `Subscription`); `TicketCategory` can
be hard-deleted but only while it has zero tickets (`is_active = false` retires it instead,
mirroring `Plan`).

- **`Ticket`** — belongs to `User` (`user()`, the requester), `TicketCategory` (`category()`,
  nullable), `User` (`agent()`, via `assigned_to`, nullable); has many `messages()`. `status`
  (`App\Enum\TicketStatus`: Open/Pending/Resolved/Closed) and `priority`
  (`App\Enum\TicketPriority`: Low/Medium/High/Urgent) are both `string` + backed-enum casts, same
  convention as the rest of the codebase.
- **`TicketCategory`** — `agents(): BelongsToMany` (`User` via `category_agent`, explicit pivot
  keys `category_id`/`user_id` since Eloquent's default FK guess from the class name would be
  wrong for a model mapped to a differently-named table) is the pool of staff eligible for
  auto-assignment on that category. `tickets(): HasMany`.
- **`TicketMessage`** — belongs to `Ticket` and (nullably) `User`. `author_type`
  (`App\Enum\TicketMessageAuthorType`: User/Staff/System) distinguishes a requester message, a
  staff reply, and an automated note (auto-assignment, reassignment, category-change re-routing) —
  System notes make the load-balancing decision visible directly in the conversation thread, not
  just the audit log.

**`App\Services\TicketAssignmentService::pickAgent(TicketCategory)`** is the load-balancing core —
pure selection, no side effects: among the category's `agents()`, picks whoever currently has the
fewest open (non-Resolved/Closed) tickets via `withCount` + `orderBy`, ties broken by user id.
Returns `null` if the category has no agents (ticket stays unassigned).

**`App\Services\TicketService`** is the only place ticket state changes (mirrors
`SubscriptionService`): `create()` (creates the ticket + first message, then auto-assigns via
`TicketAssignmentService`), `reply()` (staff reply; flips `Open` → `Pending`, never silently
overrides `Resolved`/`Closed`), `changeStatus()`, `changePriority()`, `reassign()` (manual
override — not restricted to the category's agent pool, since an admin may need to override the
automatic pick), `changeCategory()` (re-runs auto-assignment if the current agent isn't in the new
category's pool). Every mutation logs through `ActivityLogger` with module `Ticket`.

### Admin UI

`app/Livewire/Admin/Support/{Tickets,Categories}/`, routed at `admin.tickets.*` /
`admin.ticket-categories.*` (modules `tickets` — `view`/`create`/`manage` — and
`ticket_categories` — `view`/`create`/`edit`/`delete` — both in the `support` permission group).
- **Tickets** — `Index`, `Show`, `Form` (create-only — there is no public-facing submission
  channel yet per "What this is" above, so the Form lets staff log a ticket on behalf of an
  existing app user; this is also what exercises auto-assignment end-to-end today).
  `Concerns/HandlesTicketRowActions` (assign-to-me, close, reopen) is shared by Index and Show,
  same pattern as `HandlesPlanRowActions`. `Show` uses `HasShowTabs` with **Conversation**
  (message thread + reply box + a management sidebar with Status/Priority/Category/Assigned-Agent
  controls, each calling straight into `TicketService`) and **Activity** tabs.
- **Categories** — `Index`, `Form` (create/edit only, no Show page). The Form's agent checklist
  (`User::staff()`, bound via native `<x-ui.checkbox>` — the non-native Alpine checkbox only
  entangles a scalar boolean, not array membership, so a shared-`wire:model` multi-checkbox list
  needs `native`) is what actually populates `category_agent`, i.e. the load-balancing pool per
  category. `Concerns/HandlesCategoryRowActions` mirrors `HandlesPlanRowActions`'s
  guarded-delete-while-in-use pattern (`hasTickets()` in place of `hasSubscriptions()`).

`ActivityModule::TicketCategory` and `ActivityAction::Replied` were added to the shared enums for
this feature (`ActivityModule::Ticket` already existed as a placeholder before this feature was
built out). `ActivityPresenter::kind()` has explicit `ticket`/`ticket_category` module branches —
without them, a ticket's `created`/`updated`/`assigned` events would collide with the generic
User-event titles ("Account Created" etc.), since those are the same raw event strings.

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
  `guests.*` (index/show only — no create/edit, guests aren't created via the panel), `plans.*`
  (index/create/edit/show), `subscriptions.*` (index/show only — `Subscription` rows are never
  created/edited/deleted via the panel, only through `SubscriptionService`), `staff.*`
  (index/create/edit — no show page, no delete route defined yet), `roles.*` (index/create/edit),
  `activity-logs.*` (index only, read-only), `tickets.*` (index/create/show — no edit route; a
  ticket's mutable fields all change via `Show` page actions, not a form), `ticket-categories.*`
  (index/create/edit), and a single `account` route (self-service "My Account" page — no extra
  permission, every staff member can reach it).
- Each route group carries its own `permission:{module}.{action}` middleware layered on top of the
  group-level `permission:{module}.view`.

## Scheduled/queued work (`routes/console.php`)

- `PurgeExpiredAccounts` job — hourly, `withoutOverlapping()`, 1 retry (idempotent purge, next
  hourly run catches stragglers on failure), 300s timeout.
- `SyncSubscriptionStatuses` job — hourly, `withoutOverlapping()`, 1 retry, 300s timeout; scheduled
  as `new SyncSubscriptionStatuses([PaymentProvider::Local])`. Delegates to
  `SubscriptionLifecycleService::syncStatuses()` — see "Plans & Subscriptions" above.
- `activitylog:clean` Artisan command (Spatie's built-in pruning) — weekly.

## Directory map

```
app/
  Enum/            UserType, Activity{LogName,Module,Action,Context}, MailPurpose,
                   BillingInterval, PaymentProvider, SubscriptionStatus, CancelledBy, ReceiptType,
                   TicketStatus, TicketPriority, TicketMessageAuthorType
  Http/Middleware/ EnsurePanelAccess (alias: panel)
  Jobs/            PurgeExpiredAccounts, ExportActivityLog, SyncSubscriptionStatuses
  Listeners/       AuthActivityListener
  Livewire/
    Auth/          Login, Logout, VerifyEmail, PasswordReset (reset-with-token form only)
    Admin/         BaseIndex, BaseForm, BaseShow + Concerns/ (shared traits)
      Dashboard.php
      Account/Index.php                     self-service account page
      Management/Users/                     Index, Show, Form + Concerns/HandlesUserRowActions
      Management/Guests/                    Index, Show + Concerns/HandlesGuestRowActions
      Management/Plans/                     Index, Show, Form + Concerns/HandlesPlanRowActions
      Management/Subscriptions/             Index, Show + Concerns/HandlesSubscriptionRowActions
      Support/Tickets/                      Index, Show, Form + Concerns/HandlesTicketRowActions
      Support/Categories/                   Index, Form + Concerns/HandlesCategoryRowActions
      Administration/Staff/                         Index, Form (staff CRUD + role assignment)
      Administration/Roles/                         Index, Form (role/permission-matrix CRUD)
      Administration/ActivityLogs/Index.php         read-only audit viewer
      Settings/                             BaseSettings, Index, General, Mail, Policies
  Mail/            Concerns/HasMailPurpose.php (trait for purpose-based mailables),
                   Auth/VerifyEmailMail.php, Auth/ResetPasswordMail.php
  Models/          User.php (canAccessModule helper), EmailDomain.php, EmailSender.php, SmtpSetting.php, Policy.php, PolicyVersion.php, PolicyAcceptance.php,
                   Plan.php, PlanPrice.php, PlanPriceProvider.php, Subscription.php, SubscriptionReceipt.php,
                   Ticket.php, TicketCategory.php, TicketMessage.php
  Notifications/   Auth/VerifyEmailNotification.php, Auth/ResetPasswordNotification.php
  Providers/AppServiceProvider.php          CarbonImmutable default, super-admin Gate::before,
                                             module view permission inheritance policy
  Services/        AccountDeletionService, AccountMergeService, GuestConversionService, MailConfigurator, Auth/UrlResolver, SubscriptionService, SubscriptionLifecycleService,
                   TicketService, TicketAssignmentService
  Support/         ActivityLogger, ActivityLogQuery, ActivityPresenter
  Traits/          HasSubscriptions (mixed into User), HasFeatures (mixed into Plan)
config/panel.php    RBAC modules/actions/children, grace period, export threshold, seeded admin creds
database/
  migrations/       users, permission_tables (Spatie), activity_log (+ 3 hand-added indexes),
                     cache, jobs, email_domains, email_senders, smtp_settings, policies_tables,
                     plans_tables (plans/plan_prices/plan_price_providers),
                     subscriptions_tables (subscriptions/subscription_receipts),
                     tickets_table (categories/tickets/ticket_messages/category_agent)
  seeders/          DatabaseSeeder, RolesAndPermissionsSeeder (idempotent), UserSeeder,
                     EmailSendersSeeder (idempotent)
  factories/         one per model, incl. Plan/PlanPrice/PlanPriceProvider/Subscription/SubscriptionReceipt,
                     TicketCategory/Ticket/TicketMessage
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
                     MailConfiguratorTest, UrlResolverTest, VerifyEmailTest, PasswordResetTest,
                     PlansAdminTest, PlansShowTest, SubscriptionsAdminTest,
                     UserSubscriptionManagementTest, GuestSubscriptionManagementTest
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
