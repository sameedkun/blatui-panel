# Project Context

Read this file first in any new chat — it's the fast path to full context on this codebase.
`CLAUDE.md` (Laravel Boost guidelines + audit-logging rules) covers *how to write code here*;
this file covers *what the application actually is*.

## What this is

A Laravel 13 **admin panel** (BLAT stack: Blade, Livewire 4, Alpine.js v3, Tailwind v4) for
managing three kinds of accounts that all live in a single `users` table: **app users**
(the real product's end users), **guests** (anonymous/unconverted accounts), and **staff**
(admin-panel operators with RBAC roles). There is currently no public-facing product UI in
this repo — just the admin panel itself (`/*`) plus login/logout.

## Stack & versions

- PHP 8.4, Laravel 13, Livewire 4, Tailwind v4, Alpine.js v3
- `anousss007/blatui` — shadcn/ui-style copy-paste Blade components in `resources/views/components/ui/` (see BlatUI section in `CLAUDE.md`)
- `spatie/laravel-permission` — RBAC for staff (roles/permissions), guard `web`
- `spatie/laravel-activitylog` — audit trail (see "Activity logging" below)
- `spatie/laravel-sluggable` — `User::slug` generated from `name`
- `league/flysystem-aws-s3-v3` — powers a Cloudflare R2 disk (`r2` in `config/filesystems.php`, S3-compatible) for avatars/exports
- `mallardduck/blade-lucide-icons` — icon set used throughout (`<x-lucide-*>`)
- `grazulex/laravel-apiroute` — URI-path API versioning (`/api/v1/...`); see "REST API" below
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

`SetLocale` middleware (`App\Http\Middleware\SetLocale`) is stacked on the `web` group — it reads the 1-year `locale` cookie and calls `App::setLocale()`. Switched via the `<livewire:admin.components.language-switcher />` topbar header component using BlatUI dropdown. Flat localization files live in `lang/en/*.php`.

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

- **`DeletionService`** — the only place account deletion happens, for both app users
  and guests, from both the admin panel and the scheduled sweep.
  - App users: two-phase — `requestByUser()`/`requestByAdmin()` marks the account (stays live for
    `panel.account_deletion_grace_hours`, default 24h via `ACCOUNT_DELETION_GRACE_HOURS` env);
    `cancelByUser()` (only if user-initiated) / `cancelByAdmin()` (any) clears it;
    `purgeExpired()` (called hourly by `PurgeExpiredAccounts` job) permanently removes any account
    past its grace period; `instantPurgeByAdmin()` skips the grace period entirely.
  - Guests: `purgeGuestByAdmin()` skips straight to `purge()` — no request/cancel phase at all.
  - `purge()` is transactional and idempotent (`if (! $user->exists) return;`), snapshots the
    account into the audit-log properties before `forceDelete()`, and cleans up related rows
    (`deleteRelatedData()` — explicitly deletes `blocked_ips`/`personal_access_tokens`/`sessions`
    rows; `subscriptions` and `user_devices` aren't listed there since both have a `user_id` FK
    that's `cascadeOnDelete()`, so the subsequent `forceDelete()` already removes those rows
    without needing an explicit query — `subscription_receipts` cascades transitively off
    `subscriptions` the same way).
  - Scheduled purges use an explicit `causer: null` + `context: Scheduler` (no `auth()` session
    exists in that context); admin-triggered purges auto-resolve the causer/context.
- **`GuestConversionService`** — flips a guest in place into an app user (same row/id).
  `convertBySelf()` (user sets their own email+password), `convertByAdmin()` (admin sets email;
  password is a random unusable string — admin never sets/sees a real password; a reset-link send
  is TODO-deferred), `convertWithGoogle()`/`convertWithApple()` (OAuth — auto-merges into an
  existing app account if one already matches on provider-id or email). Also exposes
  `mergeByAdmin()` which delegates to `MergeService`.
- **`MergeService`** — merges a guest's identity into an *existing* app account; the
  destination survives, the guest row is `forceDelete()`d. `mergeFromProvider()` (self-service,
  OAuth-driven, no reason needed) vs `mergeByAdmin()` (requires a non-empty `$reason` — no
  provider proof, so it must be traceable as an admin judgment call). `migrateRelatedData()` is
  still a TODO stub — reassigning a guest's `subscriptions`/`user_devices` rows to the destination
  account on merge isn't wired up yet, even though both tables now exist.

These three services are why **the current uncommitted work** (per git status) exists: guest
conversion/merge functionality was just added — `MergeService` is new, and
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
built from a serializable filter-state array — so `App\Jobs\Activity\ExportActivityLog` (queued CSV export
for exports past `panel.activity_log_export_queue_threshold`, default 5000 rows) always matches
what the admin was looking at on screen. `App\Listeners\AuthActivityListener` auto-logs
login/failed-login (rate-limited)/password-reset via Laravel's event discovery — guests are never
logged for auth or account activity.

The shared activity detail dialog (`.../activity-logs/partials/detail-dialog.blade.php`, used by
both the global viewer and every per-record Activity tab) resolves its "Subject" link via
**`ActivityPresenter::subjectUrl(?Model $subject)`** — a lookup table
(`subjectUrlResolvers()`: subject class → a permission-gated closure building the right route)
covering every model actually logged as a subject (`User` — staff/app/guest each to their own
route, `Plan`, `Ticket`, `TicketCategory`, `Language`, `Notification`, `Feedback`, `Role`). Adding
support for a new subject type is one array entry there, not a new `if`/`elseif` in every view that
renders a subject link. The dialog also accepts an optional `$currentRecord` (the profile page's own
bound model, passed by the three per-record Activity tabs — Tickets, Plans, Users, and by extension
Guests since it reuses the Users partial) — when the activity's subject *is* that same record, the
subject renders as plain text with a small "Viewing" badge instead of a link back to the page
you're already on.

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
  `App\Services\Subscription\SubscriptionService` relies on. Has many `providers()` (`PlanPriceProvider`) and
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

### Webhook Notifications (`app/Livewire/Admin/Management/WebhookNotifications/`)

Raw inbound provider webhook logs (`apple_notifications` today; RevenueCat/Google/Stripe are
placeholders in the same pattern, not yet built) are a separate concern from `SubscriptionReceipt`
— a receipt is the normalized "this happened to this subscription" record, a notification row is
the unprocessed payload as the provider sent it, deduplicated by its own UUID/notification id.
Since which providers a given deployment integrates with is a per-project decision, nothing here
branches on provider name:
- **`App\Contracts\ProviderNotification`** — the one thing every provider's model agrees to expose:
  `notificationType()`, `transactionId()`, `originalTransactionId()`, `productId()`,
  `environment()`, `occurredAt()`, `isProcessed()`, `processedAt()`, `rawPayload()`. Implementations
  read their own raw columns; `notificationType()`/`environment()` stay plain strings since
  providers don't share a type vocabulary — a model may optionally expose a `notificationTypeLabel()`
  (and Apple additionally `subtypeLabel()`), discovered generically via `method_exists()` in the
  shared Blade partial, never hardcoded per provider.
- **`App\Models\Webhooks\AppleNotification`** (`apple_notifications` table) — the only implementation
  so far. `notification_type`/`subtype` cast to `App\Enum\AppleNotificationType`/
  `AppleNotificationSubtype` (Apple's closed vocabularies, labels in `enums.php` alongside
  `payment_provider`). `environment()` reads `payload.data.environment` — Apple doesn't give it its
  own column. A soft convention (not contract-enforced) that future provider tables also name their
  shared columns `transaction_id`/`original_transaction_id`/`product_id`/`processed` keeps the admin
  Index's search/filters generic across providers without per-provider query branching.
- **`App\Support\WebhookNotificationRegistry`** — same shape as
  `ActivityPresenter::subjectUrlResolvers()`: a `PaymentProvider::value → model class` array plus a
  `resolve(?PaymentProvider, ?int): ?ProviderNotification` helper. Adding RevenueCat/Google/Stripe
  later is one array line plus its model — no admin code changes.
- **`SubscriptionReceipt::notification_provider`/`notification_id`** — a deliberately loose link
  (no FK; the target table varies by provider) to the raw notification a receipt came from, resolved
  at read time via the registry through `SubscriptionReceipt::notification()`. `subscription_receipts`
  itself stays provider-agnostic — no provider-specific columns were added to it.
- **Admin UI**: `admin.webhook-notifications.*` (module `webhook_notifications`, actions `view`/
  `manage`, group `infrastructure`) is provider-filtered rather than a UNION across differently-shaped
  tables — `Index::baseQuery()` resolves to the selected provider's model via the registry, so each
  provider keeps its own native columns. `Show` takes `{provider}/{id}` route params (not
  route-model binding, since the model class varies) and 404s if the registry can't resolve it. Both
  pages, and the `webhook_notifications` tab on `Subscriptions/Show` (gated on `webhook_notifications.view`,
  separate from the existing Receipts tab), render through one generic Blade partial
  (`.../webhook-notifications/partials/detail.blade.php`) built purely off the contract — a new
  provider needs zero new Blade. Listed in the sidebar's Management section (not Application — this
  is billing/account operational tooling, same bucket as Blocked IPs, not product-content config).
- **Reprocessing**: `App\Contracts\RedispatchableNotification` (a second, optional capability
  interface separate from `ProviderNotification`, discovered via `instanceof` rather than the
  registry — not every provider needs it) exposes `redispatch(): void`. `AppleNotification`
  implements it by re-firing `App\Events\Webhooks\AppStoreWebhookReceived` (a plain event using
  `Illuminate\Foundation\Events\Dispatchable` for `::dispatch()` sugar) with the row's already-stored
  `notification_type`/`subtype`/`transaction_info`/`renewal_info`/`payload`/itself — the same event
  shape a real inbound webhook controller would dispatch. **No listener exists yet** — this is
  deliberately just the redispatch mechanism; the actual subscription-processing logic is future
  work. Index and Show both expose a permission-gated ("Process"/"Reprocess", label depends on
  `isProcessed()`) action via the shared `Concerns/HandlesWebhookNotificationRowActions` trait
  (mirrors `HandlesPlanRowActions`'s reuse pattern) — gated on `webhook_notifications.manage`
  specifically (not `.view`), since it's a real mutating action. Logs through `ActivityLogger`
  (module `ActivityModule::WebhookNotification`, reusing the `Updated` verb with a
  `type: notification_redispatched` property, per the "reusable verbs" convention) — `ActivityPresenter`
  has a matching `webhook_notification` module branch. Deliberately never touches
  `processed`/`processed_at` itself — that stays owned by whatever listener eventually gets wired up.

### User-facing subscription management

`App\Traits\HasSubscriptions` (mixed into `User`) — `subscriptions()`, `activeSubscription()` (a
`hasOne` matching trialing/active-with-`ends_at` in the future, grace-with-`grace_ends_at` in the
future, or cancelled-but-`ends_at`-still-future — i.e. cancelling doesn't cut access off until the
period actually ends, unless done "immediately"), `isSubscribed()`, `isOnTrial()`, `isInGrace()`,
`currentPlan()`, `planFeature()`. `App\Traits\HasFeatures` (mixed into `Plan`) resolves a feature
key against `config('panel.features')`'s type/default.

**`App\Services\Subscription\SubscriptionService`** is the only place subscription state changes — mirroring
`DeletionService`, it logs its own audit rows (module `User`, subject the affected user) so
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

**`App\Services\Subscription\LifecycleService`** is the counterpart for the *calendar-driven*
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
mirroring `DeletionService::purge()`'s scheduled path. Types logged: `subscription_expired`
(with a `reason` property: `trial_lapsed`/`not_recurring`/`renewal_unconfirmed`/`grace_exhausted`/
`cancellation_ended`), `subscription_trial_converted`, `subscription_entered_grace` — all three
extend `ActivityPresenter` the same way the four `SubscriptionService`-logged types do.

`App\Jobs\Subscription\SyncSubscriptionStatuses` is a thin scheduled trigger (mirrors `PurgeExpiredAccounts`):
constructor takes `array $providers = [PaymentProvider::Local]`, `handle()` just calls
`LifecycleService::syncStatuses($this->providers)`. Scheduled hourly in
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

**`App\Services\Ticket\AssignmentService`** is the single source of truth for two related things:

- **Agent eligibility** — a staff member only counts as an "agent" if they hold *both*
  `tickets.view` and `tickets.manage` (checked via Spatie's `permission()` scope, chained — two
  calls ANDed together, not one call with an array which would OR them). `eligibleAgentsQuery()`
  returns the scoped `Builder`; `eligibleAgents()`/`eligibleAgentOptions()` are the
  Collection/pluck-list conveniences built on it. Every place in the panel that offers or accepts
  an agent — the category form's checklist, the ticket filters, the bulk-assign dialog, manual
  reassignment — goes through one of these rather than re-deriving the permission check, so a
  staff member who loses ticket access stops being assignable everywhere at once.
- **`pickAgent(TicketCategory)`** — the load-balancing core, pure selection, no side effects:
  among the category's `agents()` **intersected with the eligible set**, picks whoever currently
  has the fewest open (non-Resolved/Closed) tickets via `withCount` + `orderBy`, ties broken by
  user id. Returns `null` if the category has no *eligible* agents (a category can have agents
  attached who've since lost permission — they're skipped, not just deprioritized).

**`App\Services\Ticket\TicketService`** is the only place ticket state changes (mirrors
`SubscriptionService`): `create()` (creates the ticket + first message, then auto-assigns via
`AssignmentService`), `reply()` (staff reply, optionally with file attachments — see below;
flips `Open` → `Pending`, never silently overrides `Resolved`/`Closed`), `changeStatus()`,
`changePriority()`, `reassign()` (manual override — not restricted to the category's agent pool,
since an admin may need to override the automatic pick), `changeCategory()` (re-runs
auto-assignment if the current agent isn't in the new category's pool). Every mutation logs
through `ActivityLogger` with module `Ticket`.

**Ticket visibility** (`Ticket::scopeVisibleTo(User $user)`) is the single query scope gating
which tickets a staff member can see at all: a super admin (`isSuperAdmin()`) sees everything;
anyone else sees only tickets `assigned_to` them, plus unassigned tickets in a category they're an
agent for (`agentCategories()`). Applied to `Tickets/Index`'s base query, every stat card, and both
bulk actions (so a crafted `selectedIds` payload can't reach an out-of-scope ticket) — and,
defensively, to `Tickets/Show::mount()` (`abort_unless(...->exists(), 403)`, so a non-super-admin
can't reach an out-of-scope ticket just by knowing its URL) and to
`HandlesTicketRowActions`'s lookups (assign-to-me/close/reopen).

### Message attachments

Staff can attach files when replying (`TicketService::reply()`'s `$attachments` param, an array of
`UploadedFile`). `storeAttachments()` writes each one via `$file->store($ticket->attachmentsPath())`
— **no disk named**, so it follows whatever `filesystems.default` resolves to (same convention as
`User::avatarUrl()`) — and records `{name, size, mime, path}` per file into
`ticket_messages.attachments` (`array` cast). Only the **path** is stored, never a resolved URL;
`TicketMessage::attachmentsWithUrls()` resolves `Storage::url()` per attachment at render time.
`Ticket::attachmentsPath()` (`ticket-attachments/{id}`) is the **single folder every message's
attachments for that ticket share** — not per-message subfolders — specifically so `Ticket::booted()`'s
`deleting` event can remove everything in one `Storage::deleteDirectory()` call when a ticket is
deleted (DB-level FK cascade already removes the `ticket_messages` rows; that event is what actually
removes the files). The reply form uses BlatUI's `file-upload` component (`php artisan blatui:add
file-upload`, newly added for this) bridged to Livewire's `WithFileUploads` on `Tickets/Show`
(`$replyAttachments`, validated: max 5 files, 10MB each, an image/doc/archive mime allowlist). Its
`wire:key` is tied to an incrementing `replyAttachmentsKey` bumped after every successful reply —
the component keeps its own client-side (Alpine) preview list that a normal Livewire morph won't
clear just because the server-side property was reset, so the key change forces a fresh remount
instead. After a successful reply the component also dispatches `scroll-to-latest-message`, which
the conversation tab's thread card listens for to scroll itself into view and its internal chat box
down to the new message (`x-ref`s, no polling).

### Ticket lifecycle sweeps

**`App\Services\Ticket\LifecycleService`** is the calendar-driven counterpart to `TicketService`,
mirroring the `SubscriptionService`/`LifecycleService` split — these two run off the
scheduler, not in response to a staff action:
- `autoCloseInactive(int $days)` (`panel.ticket_auto_close_inactive_days`, default 7) — closes every
  Pending/Resolved ticket whose `last_staff_response_at` is at least that old **and** hasn't had a
  requester reply since (`last_user_response_at` null or older than the staff message — i.e. staff
  had the last word). Leaves a `System` thread message ("Automatically closed after N days of
  inactivity.") and emails the requester via `TicketAutoClosedNotification` →
  `App\Mail\Support\TicketAutoClosedMail` (`MailPurpose::Support`).
- `purgeClosedTickets(int $months)` (`panel.ticket_purge_closed_after_months`, default 6) —
  permanently deletes every ticket that's been `Closed` for at least that long. Just calls
  `$ticket->delete()`: the model's `deleting` event removes the attachments folder first, the DB-level
  FK cascade removes every `ticket_messages` row, then the ticket row itself goes — snapshotted into
  the audit log first (subject/requester/category as properties) since Spatie's subject reference
  survives the row being gone but can no longer be resolved live.

Both log with `causer: null` + `ActivityContext::Scheduler` (module `Ticket`, reusing `Updated`/
`Deleted` with a distinguishing `type` property rather than new verbs). `App\Jobs\
{CloseInactiveTickets,PurgeClosedTickets}` are thin scheduled triggers (mirroring
`PurgeExpiredAccounts`) reading their threshold from config; both scheduled daily in
`routes/console.php` since the thresholds are day/month-granularity.

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

## Device Management & IP Blocking

`app/Models/{UserDevice,BlockedIp}.php`, backed by the `user_devices` and `blocked_ips` tables.
Auth for the (not-yet-built) mobile/API surface is Laravel Sanctum personal access tokens; these
two features track which device holds each token and let both the user and an admin kill that
access, plus block traffic by IP.

- **`UserDevice`** — one row per physical device, holding only its *current* token
  (`token_id`, nullable FK to `personal_access_tokens`) — deliberately no login-history table.
  `ulid` (generated in `booted()`, like `User::external_id`) is the public handle; `id` stays the
  PK for FKs. `device_fingerprint` is stored as a sha256 hash, never plaintext, and is unique per
  `(user_id, device_fingerprint)` — a re-login on the same physical device updates the existing
  row rather than creating a second one. `device_type` casts to `App\Enum\DeviceType`
  (`Mobile`/`Tablet`/`Desktop`/`Web` — form factor; `platform`/`os` stay free-text columns for
  OS-level detail). Status is derived, never stored as a boolean: `is_active`/`is_blocked`/
  `is_revoked` accessors and matching `active()`/`revoked()`/`blocked()` scopes read off
  `revoked_at`/`blocked_at` (blocked wins visual priority when both could apply). A model
  `updated` hook deletes the associated `personal_access_tokens` row and nulls `token_id`
  whenever `revoked_at` or `blocked_at` is freshly set — revoking/blocking always kills API access
  immediately, never just marks it.
- **`BlockedIp`** — `user_id` nullable (`null` = global block, matching every user). A DB-generated
  `user_scope` column (`COALESCE(user_id, 0)`) backs a `(ip_address, user_scope)` unique
  constraint — one global block per IP, one per-user block per `(IP, user)` pair, enforced at the
  DB layer, not just in the UI. `active()` scope excludes anything past `expires_at`; `recordHit()`
  increments `hits` + stamps `last_hit_at` in one query. `user_id`'s FK is `restrictOnDelete()`,
  not `cascadeOnDelete()` — InnoDB (MySQL error 1215) refuses a cascading `ON DELETE`/`ON UPDATE`
  action on a column that a stored generated column depends on, and `user_scope` depends on
  `user_id`. `DeletionService::deleteRelatedData()` explicitly deletes a user's
  `blocked_ips` rows before `forceDelete()` instead, so the restriction is never actually hit.

**`App\Services\Device\DeviceService`** is the only place device rows are mutated (mirrors
`DeletionService`'s "one service, everything goes through here" shape). `register()` hashes
the incoming fingerprint, locks the *user* row (`lockForUpdate()`, not just the device row — two
concurrent logins for two different fingerprints would otherwise both pass the limit check) inside
a transaction, checks the caller's plan `device_limit` feature (`$user->planFeature('device_limit',
...)` from `HasSubscriptions`/`HasFeatures`, already used by Plans/Subscriptions) only when the
matched device is new or currently revoked — an already-active device's re-login never counts
against the limit — then `updateOrCreate`s the row, retrying once past a `QueryException` race on
the unique constraint. Throws `App\Exceptions\DeviceLimitExceededException` /
`DeviceBlockedException` (a blocked device can never reactivate by logging in again, only via
`unblock()`). `revoke()`/`revokeAll()`/`block()`/`unblock()` are the only audited device
mutations — deliberately, `register()`/`touch()` never write an audit-log entry, since this app's
"no login history" design would otherwise be defeated by every login becoming an auditable event.
`touch()` (called by `EnsureDeviceIsValid`) throttles its own write to once per 5 minutes. No
geo-IP resolution package is installed — `city`/`country`/`country_code` are only ever populated
from client-supplied `App\Support\DeviceData` at registration time, never resolved from the
request IP.

**Middleware**: `EnsureDeviceIsValid` (alias `device.valid`, applied to the whole authenticated API
group in `routes/api.php`) resolves the device from the current Sanctum token and 401s with a
`DEVICE_REVOKED`/`DEVICE_BLOCKED` machine-readable code if it's missing, revoked, or blocked, then
calls `DeviceService::touch()`. `CheckBlockedIp` is prepended to the whole `api` middleware group
in `bootstrap/app.php` (`$middleware->api(prepend: [...])`) so it runs *before* `auth:sanctum` —
since the user isn't resolved yet at that point, it independently peeks the bearer token via
`PersonalAccessToken::findToken()` to still support per-user blocks that early. Match result is
cached 60s (keyed `blocked-ip:{ip}:{userId|guest}`, via the default `Cache` facade — this app's
`.env` already points `CACHE_STORE` at Redis); a hit dispatches the queued `RecordBlockedIpHit` job
rather than writing synchronously, since this runs on every single API request.

A minimal self-service API exists at `App\Http\Controllers\Api\DeviceController` (`GET
/api/devices`, `DELETE /api/devices/{ulid}`) — list/revoke *your own* devices, scoped to
`$request->user()->devices()`; a ulid for another account's device 404s via `firstOrFail()`, never
a manual 403. There is deliberately no login/device-registration endpoint yet — `DeviceService::
register()` is a ready wiring point for whenever a real auth/login controller is built, same as
`GuestConversionService`'s deferred email-verification TODOs.

**Admin panel** (`app/Livewire/Admin/Management/{Devices,BlockedIps}/`): `Devices/Index` is the
global, filterable device list (`admin.devices.index`, shows a User column) — a normal
route-bound component like every other Index page in this app, not a nested/embedded one.
Block/unblock/revoke live in `Concerns/HandlesDeviceRowActions` (mirrors `HandlesPlanRowActions`),
confirmed via the standard `x-admin.confirm-dialog` / `x-admin.reason-dialog` components the rest
of the app uses (Devices originally used the right-side drawer variants like BlockedIps below, but
was switched to match the rest of the admin panel). `Devices/SharedFingerprints` is a separate
page/query (`GROUP BY device_fingerprint HAVING COUNT(DISTINCT user_id) > 1`) rather than an Index
filter.

BlockedIps' own confirmations (delete/delete-all-expired, the IP-activity panel) still use
right-side drawers rather than dialogs (per-feature preference) — two reusable partials,
`x-admin.confirm-drawer` / `x-admin.reason-drawer`, mirror `confirm-dialog`/`reason-dialog` but
wrap BlatUI's `drawer` component (`direction="right"`). BlatUI's own `drawer.blade.php` was
extended with the same `id`-driven dispatchable-open/close prop `dialog.blade.php` already had
(`$dispatch('open-drawer-{id}')`) — upstream only exposed `direction`.

The Users/Show `devices` tab does **not** embed `Devices/Index` — it's a plain Blade partial (like
every other tab in this app) fed by `Show::deviceHistory()`, with its own scoped copy of
block/unblock/revoke plus a revoke-all action living directly on `Show` (mirroring how that page's
subscription actions are bespoke rather than reused from a shared trait) rather than sharing
`HandlesDeviceRowActions` — that trait's lookups are deliberately unscoped (any device, matching
the global index's investigator role), while the profile tab's lookups are scoped to
`$this->record->devices()` so a crafted ulid can never reach another account's device from a
specific user's page.

`BlockedIps/Index` (a normal `BaseIndex` page) keeps its create/edit drawer state and the
IP-activity-panel drawer directly on itself (`Concerns/HandlesBlockedIpForm`,
`Concerns/HandlesIpActivityPanel`) rather than as separate nested components — this follows the
existing single-component-owns-its-dialog-state pattern (`Users/Show`'s Assign-Plan dialog is the
same shape). Picking Global scope in the form requires an explicit second confirmation
(`globalConfirmed`, disables Save until checked) showing the IP's distinct-user count over the
last 30 days, with an extra carrier-NAT warning above `panel.blocked_ip_carrier_nat_threshold`
(default 10) — Pakistani/Indian mobile networks routinely put hundreds of unrelated users behind
one address. Creating a global block also `Log::warning()`s loudly. There is no Show page for a
blocked IP — `HandlesIpActivityPanel`'s drawer (gated `devices.view`, since it surfaces emails) is
what a Show page would have been.

**Permissions**: two new modules in `config/panel.php` — `devices` (group `management`: `view`,
`investigate`, `block`, `unblock`, `revoke`, `export`) and `blocked-ips` (group `infrastructure` —
previously an unused group label; `view`, `create`, `create-global`, `update`, `delete`). The
action vocabulary gained `investigate`/`block`/`unblock`/`revoke`/`update`/`create-global` to
support them. `blocked-ips.create-global` is excluded from the seeded `admin` role
(`admin_excluded_permissions`) — a global block can lock out thousands of paying users at once, so
it's reserved for senior staff, same reasoning as the other exclusions there. Note the `blocked-ips`
module key is hyphenated (unlike `ticket_categories`, which is underscored with a hyphenated
*route* prefix) — a deliberate one-off to match this feature's literal permission-string spec.

**Scheduled**: `PruneExpiredBlockedIps` (daily) deletes `blocked_ips` past `expires_at`;
`PruneRevokedDevices` (monthly, delegates to `DeviceService::pruneRevoked()`) deletes `user_devices`
revoked (never blocked) more than `panel.user_device_revoked_retention_months` (default 6) ago —
blocked devices are never swept by retention, since `blocked_reason` is the only record of why.

`ActivityModule::Device`/`BlockedIp` and `ActivityAction::Blocked`/`Unblocked`/`Revoked` were added
to the shared enums; `ActivityPresenter` has `device`/`blocked_ip` branches (same pattern as the
`ticket`/`ticket_category` ones) so these events read as "Device Blocked"/"IP Block Created" etc.
rather than colliding with generic titles.

## REST API

The mobile/API surface (currently just device self-service — see "Device Management & IP
Blocking" above) is versioned by URI path via `grazulex/laravel-apiroute`, configured entirely
through `config/apiroute.php`'s `versions` map — not by hand-writing `Route::prefix()` groups.
Only `v1` exists today (`status => 'active'`).
- Each version's routes live in their own file (`routes/api/v1.php`), required by the package's
  service provider at boot from the config entry — `routes/api.php` (the file
  `bootstrap/app.php`'s `withRouting(api: ...)` actually loads) is deliberately left empty aside
  from a pointer comment; it only exists because Laravel's routing config requires an entry point.
  `config/apiroute.php`'s `v1` entry deliberately sets **no** version-level `middleware` — `v1`
  will eventually mix authenticated endpoints with guest ones (login, signup, ...) in the same
  file, so `auth:sanctum` + `device.valid` is applied to an explicit `Route::middleware([...])
  ->group(...)` inside `routes/api/v1.php` itself, with guest routes living outside that group in
  the same file, rather than blanket at the version-config level.
- **Controllers**: `App\Http\Controllers\Api\ApiController` (unversioned, shared) is the base every
  `App\Http\Controllers\Api\V1\*` controller extends — it exposes `success()`/`created()`/
  `noContent()`/`error()`/`notFound()`/`unauthorized()`/`forbidden()`/`validationError()` as
  **static** methods (called the normal instance way, `$this->success(...)` — PHP allows invoking
  a static method through `->`) so `App\Exceptions\Api\ApiExceptionRenderer` (see below), which
  runs outside any controller instance, can reuse the exact same envelope builders instead of a
  separate `Support` class duplicating them. A `V2` controller base, if one is ever needed, would
  extend `ApiController` rather than duplicate it.
- **Requests**: `App\Http\Requests\V1\{Resource}\*Request.php` — no `Api\` segment, since this
  namespace is API-only in this app (the admin panel is all Livewire; it never uses FormRequests).
  None exist yet — this is the ready convention for the first one.
- **Resources**: `App\Http\Resources\V1\{Resource}.php` (e.g. `UserDeviceResource`) — folder-versioned
  to match Controllers/Requests, not suffix-versioned (no `ResourceV2` classes). A resource is only
  forked into a `V2` folder if a version genuinely needs a different shape for it; most resources
  stay shared indefinitely.
- **Response envelope**: `ApiController::success()`/`error()` (static, see above) is the single
  source of truth for the `{status, message, data}` / `{status, message, errors?}` shape.
  `App\Exceptions\Api\ApiExceptionRenderer` (registered from `bootstrap/app.php`'s
  `withExceptions()`) calls `ApiController::error()` directly to reformat framework-thrown
  `ValidationException`/`AuthenticationException`/`HttpExceptionInterface` (covers
  `NotFoundHttpException`, `AccessDeniedHttpException` from a converted `AuthorizationException`,
  etc.) and any other `Throwable` into that same envelope for API requests — so a 422 from a
  FormRequest looks identical to one built by hand via `validationError()`.
  `JsonResource::withoutWrapping()` is set in `AppServiceProvider::configureDefaults()` so a
  Resource nested inside `success()`'s own `data` key doesn't double-wrap under two `data` keys.
- **`App\Support\ApiRequest::targets(Request)`** decides whether a request belongs to the API
  surface at all — used both by `bootstrap/app.php`'s `shouldRenderJsonWhen()` and by every
  `ApiExceptionRenderer` method's null-guard, instead of a hardcoded `$request->is('api/*')`
  repeated in five places. It checks (in order): whether laravel-apiroute's `ResolveApiVersion`
  middleware already stamped `api_version` onto the request (works under *any* detection strategy —
  uri, header, query, accept — and is set for every matched, versioned request regardless of URL
  shape); then falls back to `config('apiroute.strategies.uri')`'s own `domain`/`prefix` for
  requests that never matched a route at all (a typo'd endpoint still needs a JSON 404, not HTML,
  but never reaches `ResolveApiVersion` since no route matched). This is what makes the check
  survive a future move to domain-based routing (e.g. `api.example.com` serving bare `/v1/...`
  paths with `strategies.uri.prefix` set to `''`) without touching this code — only the config
  changes.
- laravel-apiroute's own version-negotiation errors (unknown/sunset version) render through the
  package's own JSON shape, not `ApiController`'s envelope — left as-is, since that's a distinct
  concern (version negotiation, not endpoint-level success/error) and the package's shape is
  already sensible.

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
  (index/create/edit), `devices.*` (index only, plus `shared-fingerprints` gated
  `devices.investigate`), `blocked-ips.*` (index only — create/edit/delete are drawer actions on
  the index, not routes), `webhook-notifications.*` (index only, plus `show` at `{provider}/{id}` —
  not route-model bound, since the model class varies by provider), and a single `account` route
  (self-service "My Account" page — no extra permission, every staff member can reach it).
- Each route group carries its own `permission:{module}.{action}` middleware layered on top of the
  group-level `permission:{module}.view`.
- `routes/api/v1.php` (registered via `config/apiroute.php`, see "REST API" above) — `GET
  /api/v1/user`, `GET /api/v1/devices` / `DELETE /api/v1/devices/{ulid}` (the self-service device
  endpoints, see "Device Management & IP Blocking" above), all under `auth:sanctum + device.valid`
  (`EnsureDeviceIsValid`) via the version's config-level middleware. `CheckBlockedIp` isn't
  route-level — it's prepended to the whole `api` middleware group in `bootstrap/app.php` instead,
  since it has to run before `auth:sanctum` resolves the user (and laravel-apiroute's own
  version-registered routes explicitly include the `api` group, so this still applies to them).

## Scheduled/queued work (`routes/console.php`)

- `PurgeExpiredAccounts` job — hourly, `withoutOverlapping()`, 1 retry (idempotent purge, next
  hourly run catches stragglers on failure), 300s timeout.
- `SyncSubscriptionStatuses` job — hourly, `withoutOverlapping()`, 1 retry, 300s timeout; scheduled
  as `new SyncSubscriptionStatuses`; the job selects supported providers itself. Delegates to
  `LifecycleService::syncStatuses()` — see "Plans & Subscriptions" above.
- `CloseInactiveTickets` / `PurgeClosedTickets` jobs — both daily, `withoutOverlapping()`, 1
  retry, 300s timeout; delegate to `LifecycleService` — see "Ticket lifecycle sweeps" above.
- `PruneExpiredBlockedIps` job — daily, `withoutOverlapping()`, 1 retry, 300s timeout.
- `PruneRevokedDevices` job — monthly, `withoutOverlapping()`, 1 retry, 300s timeout; delegates to
  `DeviceService::pruneRevoked()` — see "Device Management & IP Blocking" above.
- `activitylog:clean` Artisan command (Spatie's built-in pruning) — weekly.

All jobs log terminal failures to the daily `jobs` channel (`storage/logs/jobs-*.log`) with their
class name and exception. Scheduled jobs read their own operational configuration at execution
time, leaving `routes/console.php` responsible only for cadence and overlap protection.

## Directory map

```
app/
  Contracts/       ProviderNotification (webhook-notification presentation contract)
  Enum/            UserType, Activity{LogName,Module,Action,Context}, MailPurpose,
                   BillingInterval, PaymentProvider, SubscriptionStatus, CancelledBy, ReceiptType,
                   TicketStatus, TicketPriority, TicketMessageAuthorType, DeviceType,
                   AppleNotificationType, AppleNotificationSubtype
  Exceptions/      DeviceLimitExceededException, DeviceBlockedException,
                   Api/ApiExceptionRenderer (unifies framework exceptions into ApiResponse's envelope)
  Http/Controllers/Api/ApiController.php        unversioned base (success/error/etc. helpers)
                   Api/V1/DeviceController.php  self-service list/revoke own devices
  Http/Middleware/ EnsurePanelAccess (alias: panel), EnsureDeviceIsValid (alias: device.valid),
                   CheckBlockedIp (prepended to the global `api` middleware group)
  Http/Resources/V1/UserDeviceResource.php
  Jobs/            Account/PurgeExpiredAccounts, Activity/ExportActivityLog,
                   Auth/{PruneExpiredBlockedIps, RecordBlockedIpHit},
                   Device/{PruneRevokedDevices, ResolveDeviceLocation},
                   Notification/SendPushNotification, Subscription/SyncSubscriptionStatuses,
                   Ticket/{CloseInactiveTickets, PurgeClosedTickets}
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
      Management/Devices/                   Index, SharedFingerprints + Concerns/HandlesDeviceRowActions
      Management/BlockedIps/                Index + Concerns/{HandlesBlockedIpForm,HandlesIpActivityPanel}
      Management/WebhookNotifications/      Index, Show (provider-filtered raw webhook log)
      Support/Tickets/                      Index, Show, Form + Concerns/HandlesTicketRowActions
      Support/Categories/                   Index, Form + Concerns/HandlesCategoryRowActions
      Administration/Staff/                         Index, Form (staff CRUD + role assignment)
      Administration/Roles/                         Index, Form (role/permission-matrix CRUD)
      Administration/ActivityLogs/Index.php         read-only audit viewer
      Settings/                             BaseSettings, Index, General, Mail, Policies
  Mail/            Concerns/HasMailPurpose.php (trait for purpose-based mailables),
                   Auth/VerifyEmailMail.php, Auth/ResetPasswordMail.php, Support/TicketAutoClosedMail.php
  Models/          User.php (canAccessModule helper), EmailDomain.php, EmailSender.php, SmtpSetting.php, Policy.php, PolicyVersion.php, PolicyAcceptance.php,
                   Plan.php, PlanPrice.php, PlanPriceProvider.php, Subscription.php, SubscriptionReceipt.php,
                   Ticket.php, TicketCategory.php, TicketMessage.php, UserDevice.php, BlockedIp.php
    Webhooks/      AppleNotification.php (implements ProviderNotification; RevenueCat/Google/Stripe
                   are future additions in the same subnamespace, not yet built)
  Notifications/   Auth/VerifyEmailNotification.php, Auth/ResetPasswordNotification.php,
                   Support/TicketAutoClosedNotification.php
  Providers/AppServiceProvider.php          CarbonImmutable default, super-admin Gate::before,
                                             module view permission inheritance policy
  Services/        Account/{DeletionService, MergeService, GuestConversionService}, Auth/UrlResolver,
                   Device/{DeviceService, LocationService}, Mail/Configurator, Notification/OneSignalService,
                   Subscription/{LifecycleService, SubscriptionService},
                   Ticket/{AssignmentService, LifecycleService, TicketService}
  Support/         ActivityLogger, ActivityLogQuery, ActivityPresenter, DeviceData,
                   WebhookNotificationRegistry (provider → notification-model registry),
                   ApiRequest (decides whether a request targets the API surface — see "REST API")
  Traits/          HasSubscriptions (mixed into User), HasFeatures (mixed into Plan)
config/panel.php    RBAC modules/actions/children, grace period, export threshold, seeded admin creds
config/apiroute.php grazulex/laravel-apiroute — API version registry (see "REST API" above)
database/
  migrations/       users, permission_tables (Spatie), activity_log (+ 3 hand-added indexes),
                     cache, jobs, email_domains, email_senders, smtp_settings, policies_tables,
                     plans_tables (plans/plan_prices/plan_price_providers),
                     subscriptions_tables (subscriptions/subscription_receipts),
                     tickets_table (categories/tickets/ticket_messages/category_agent),
                     user_devices_table, blocked_ips_table (generated `user_scope` column backing
                     its unique constraint), apple_notifications_table (subscriptions_tables' own
                     `subscription_receipts` block carries the loose `notification_provider`/
                     `notification_id` link columns directly, no separate migration)
  seeders/          DatabaseSeeder, RolesAndPermissionsSeeder (idempotent), UserSeeder,
                     EmailSendersSeeder (idempotent)
  factories/         one per model, incl. Plan/PlanPrice/PlanPriceProvider/Subscription/SubscriptionReceipt,
                     TicketCategory/Ticket/TicketMessage, UserDevice, BlockedIp, Webhooks/AppleNotification
resources/
  views/components/ui/       BlatUI copy-paste components (x-ui.*) — see CLAUDE.md BlatUI section;
                              `drawer` extended with the same id-driven open/close prop `dialog` has
  views/components/admin/    panel-specific composites: filter-bar (now also a `text` filter type),
                              page-header, pagination, confirm-dialog, reason-dialog, confirm-drawer,
                              reason-drawer, device-status-badge, show-tabs, stat-card, dropdown, tooltip
  views/layouts/admin/       app.blade.php (sidebar shell), guest.blade.php (login)
  views/livewire/admin/      one folder per Livewire component, mirroring app/Livewire/Admin
  css/blatui.css             design tokens (CSS vars on :root/.dark/[data-*])
tests/Feature/       AccountDeletionTest, PurgeExpiredAccountsTest, ActivityLogTest,
                     ActivityLogsViewerTest, UserShowTest, GuestsIndexTest, GuestShowTest,
                     GuestConversionServiceTest, EmailSenderResolutionTest, SettingsMailTest,
                     MailConfiguratorTest, UrlResolverTest, VerifyEmailTest, PasswordResetTest,
                     PlansAdminTest, PlansShowTest, SubscriptionsAdminTest,
                     UserSubscriptionManagementTest, GuestSubscriptionManagementTest,
                     DeviceServiceTest, DeviceApiTest, ApiExceptionRenderingTest, BlockedIpTest,
                     DevicesAdminTest, BlockedIpsAdminTest, WebhookNotificationTest,
                     WebhookNotificationsAdminTest
```

## Known rough edges / deferred work (don't be surprised by these)

- `TicketAutoClosedMail` tells the requester "reply to this ticket and it will be reopened
  automatically" — there's no mechanism that does that yet (no public-facing ticket UI or
  inbound-email parsing per "What this is" above), so this is aspirational copy matching a
  future capability, not a working link/flow today. Reopening currently only happens via the
  admin panel (`TicketService::changeStatus()`).
- No listener is wired up to `App\Events\Webhooks\AppStoreWebhookReceived` yet — the admin
  "Reprocess"/"Process" action on a webhook notification (see "Webhook Notifications" above) fires
  the event but nothing currently handles it, so `processed`/`processed_at` never actually change
  from that button today. There's also no inbound webhook controller yet that would dispatch this
  event from a real Apple delivery — `apple_notifications` rows currently only get created by tests
  and manual seeding.
- `UserSeeder` assigns `config('panel.app_user_role')` to the local test user, but `panel.php`
  only defines `super_admin_role` — app users/guests are distinguished by `type`, not roles, so
  this key doesn't exist. Local-only seeding path; harmless but dead config lookup.
  - Note: app users are never assigned Spatie roles anywhere else in the codebase — this line
    looks like leftover/aspirational code.
- Several TODOs mark intentionally-deferred wiring: email verification notifications on guest
  conversion, password-reset-link dispatch on admin-initiated conversion, and
  `MergeService::migrateRelatedData()`'s `user_devices` reassignment — a guest's devices
  aren't moved to the destination account on merge yet. Its `subscriptions` reassignment *is*
  wired up: every guest subscription is reassigned to the destination up front (so history
  survives the guest's `forceDelete()`), and if both accounts have an active subscription the
  destination's wins and the guest's is cancelled — except a `local` (no real gateway) app
  subscription always loses to a real external guest subscription, which is reassigned and
  linked via `previous_subscription_id` instead.
- There is deliberately no login/device-registration API endpoint yet — see "Device Management &
  IP Blocking" above. `DeviceService::register()` is a ready wiring point for whenever one is built.
- `Users/Show.php` actions (`verifyEmailManually`, `resendVerificationEmail`, `sendPasswordResetLink`) are fully wired up to Laravel's email verification and password reset broker flows.
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
