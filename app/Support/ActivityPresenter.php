<?php

namespace App\Support;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityLogName;
use App\Enum\ActivityModule;
use App\Enum\PaymentProvider;
use App\Enum\PolicyType;
use App\Enum\TicketPriority;
use App\Models\Feedback;
use App\Models\Language;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\Webhooks\AppleNotification;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

/**
 * Turns one raw {@see Activity} row into display-ready data for the per-record
 * "Activity" tab timeline (shared by the Users and Guests profile pages). Pure
 * presentation only — it never writes activity, only reads it.
 */
class ActivityPresenter
{
    public static function actionLabel(?string $action): string
    {
        return ActivityAction::tryFrom((string) $action)?->label() ?? self::fallbackLabel($action);
    }

    public static function moduleLabel(?string $module): string
    {
        return ActivityModule::tryFrom((string) $module)?->label() ?? self::fallbackLabel($module);
    }

    public static function contextLabel(?string $context): string
    {
        return ActivityContext::tryFrom((string) $context)?->label() ?? self::fallbackLabel($context);
    }

    public static function categoryLabel(?string $category): string
    {
        return ActivityLogName::tryFrom((string) $category)?->label() ?? self::fallbackLabel($category);
    }

    public static function fieldLabel(string $field): string
    {
        $key = "activity_logs.fields.{$field}";
        $translation = __($key);

        return $translation === $key ? Str::headline($field) : $translation;
    }

    public static function areaLabel(string $area): string
    {
        $key = "activity_logs.areas.{$area}";
        $translation = __($key);

        return $translation === $key ? Str::headline($area) : $translation;
    }

    public static function subjectTypeLabel(?string $subjectType): string
    {
        if (! $subjectType) {
            return '—';
        }

        $basename = class_basename($subjectType);
        $key = 'activity_logs.subject_types.'.Str::snake($basename);
        $translation = __($key);

        return $translation === $key ? Str::headline($basename) : $translation;
    }

    public static function formatValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? __('activity_logs.values.yes') : __('activity_logs.values.no'),
            $value === null => '—',
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }

    protected static function fallbackLabel(?string $value): string
    {
        return $value ? Str::headline($value) : '—';
    }

    /**
     * Orders an activity's raw properties for the full-detail dialog. MySQL's
     * JSON storage does not preserve insertion order (object members come
     * back sorted by key length, then lexically), so the dialog can't just
     * trust whatever order the column hands back. Rather than maintain a
     * whitelist of every action-specific key (which drifts as actions are
     * added), this only pins the small, stable set of forensic/technical
     * fields to the end — everything else keeps whatever order it already has.
     *
     * @param  Collection<string, mixed>  $properties
     * @return Collection<string, mixed>
     */
    public static function orderProperties(Collection $properties): Collection
    {
        $technical = ['ip', 'user_agent'];

        return $properties->sortBy(function ($value, string $key) use ($technical): int {
            $rank = array_search($key, $technical, true);

            return $rank === false ? 0 : 1000 + $rank;
        });
    }

    /**
     * @return array{
     *     icon: string,
     *     colorClass: string,
     *     title: string,
     *     timestamp: string,
     *     rows: array<int, array{label: string, value: string|array<int, string>}>,
     * }
     */
    public static function present(Activity $activity, ?Model $subject = null): array
    {
        /** @var array<string, mixed> $properties */
        $properties = $activity->properties->toArray();
        $kind = self::kind((string) $activity->event, $properties);

        return [
            'icon' => self::icon($kind),
            'colorClass' => self::colorClass($kind),
            'title' => self::title($kind, $properties),
            'timestamp' => self::timestamp($activity->created_at),
            'rows' => self::rows($kind, $activity, $properties, $subject),
        ];
    }

    /**
     * A single 'updated' activity may represent a profile edit, a password
     * change, or both at once (see HandlesUserRowActions / Form::save()) — this
     * collapses that into a distinct display "kind" so a password-only update
     * reads as "Password changed" rather than an empty "Profile updated".
     *
     * Every Settings page (Mail, Policies, …) logs everything under one module
     * ("setting") regardless of which section changed (SMTP, a domain, a
     * purpose sender, a test send, a policy document) — `properties.area` is
     * the only thing that tells those apart, so it's folded into the kind
     * here rather than left for every other method to re-derive.
     *
     * Policy areas are `policy_{PolicyType::value}` (e.g. `policy_privacy`)
     * and all collapse into one `setting_policy_updated` kind — adding a new
     * {@see PolicyType} case needs no change here; {@see title()} derives the
     * specific "X Updated" wording from the enum at render time.
     *
     * @param  array<string, mixed>  $properties
     */
    protected static function kind(string $event, array $properties): string
    {
        $type = $properties['type'] ?? null;
        if (($properties['module'] ?? null) === 'user' && is_string($type) && str_starts_with($type, 'subscription_')) {
            return $type;
        }

        if (($properties['module'] ?? null) === 'plan') {
            return match ($event) {
                'created' => 'plan_created',
                'updated' => 'plan_updated',
                'deleted' => 'plan_deleted',
                default => $event,
            };
        }

        if (($properties['module'] ?? null) === 'ticket') {
            return match ($event) {
                'created' => 'ticket_created',
                'updated' => 'ticket_updated',
                'assigned' => 'ticket_assigned',
                'replied' => 'ticket_replied',
                default => $event,
            };
        }

        if (($properties['module'] ?? null) === 'ticket_category') {
            return match ($event) {
                'created' => 'ticket_category_created',
                'updated' => 'ticket_category_updated',
                'deleted' => 'ticket_category_deleted',
                default => $event,
            };
        }

        if (($properties['module'] ?? null) === 'device') {
            return match ($event) {
                'blocked' => 'device_blocked',
                'unblocked' => 'device_unblocked',
                'revoked' => 'device_revoked',
                default => $event,
            };
        }

        if (($properties['module'] ?? null) === 'blocked_ip') {
            return match ($event) {
                'created' => 'blocked_ip_created',
                'updated' => 'blocked_ip_updated',
                'deleted' => 'blocked_ip_deleted',
                default => $event,
            };
        }

        if (($properties['module'] ?? null) === 'webhook_notification') {
            return 'webhook_notification_redispatched';
        }

        if (($properties['module'] ?? null) === 'setting') {
            $area = $properties['area'] ?? null;

            if (is_string($area) && str_starts_with($area, 'policy_')) {
                return 'setting_policy_updated';
            }

            return match ($area) {
                'smtp' => 'setting_smtp',
                'email_domain' => match ($event) {
                    'created' => 'setting_domain_created',
                    'deleted' => 'setting_domain_deleted',
                    default => 'setting_domain_updated',
                },
                'email_sender' => 'setting_sender_updated',
                'test_email' => 'setting_test_email',
                default => $event,
            };
        }

        if ($event === 'updated' && empty($properties['attributes']) && ($properties['password_changed'] ?? false)) {
            return 'password_changed';
        }

        return $event;
    }

    protected static function icon(string $kind): string
    {
        return match ($kind) {
            'created' => 'user-plus',
            'updated' => 'user',
            'password_changed', 'password_reset' => 'lock',
            'deleted' => 'trash',
            'restored' => 'rotate-ccw',
            'force_deleted', 'purged' => 'trash-2',
            'banned' => 'shield-ban',
            'unbanned' => 'shield-check',
            'assigned' => 'key',
            'login' => 'log-in',
            'failed' => 'triangle-alert',
            'deletion_requested' => 'clock',
            'deletion_cancelled' => 'circle-check',
            'converted' => 'repeat',
            'merged' => 'git-merge',
            'setting_smtp' => 'server',
            'setting_domain_created', 'setting_domain_updated', 'setting_domain_deleted' => 'globe',
            'setting_sender_updated' => 'at-sign',
            'setting_test_email' => 'send',
            'setting_policy_updated' => 'file-text',
            'plan_created' => 'package-plus',
            'plan_updated' => 'package',
            'plan_deleted' => 'package-x',
            'ticket_created' => 'life-buoy',
            'ticket_updated' => 'ticket',
            'ticket_assigned' => 'user-check',
            'ticket_replied' => 'message-circle',
            'ticket_category_created', 'ticket_category_updated' => 'tags',
            'ticket_category_deleted' => 'tag',
            'subscription_assigned' => 'credit-card',
            'subscription_upgraded' => 'arrow-up-circle',
            'subscription_cancelled' => 'circle-slash',
            'subscription_reactivated' => 'refresh-cw',
            'subscription_trial_converted' => 'badge-check',
            'subscription_entered_grace' => 'triangle-alert',
            'subscription_expired' => 'circle-x',
            'device_blocked' => 'shield-ban',
            'device_unblocked' => 'shield-check',
            'device_revoked' => 'shield-off',
            'blocked_ip_created' => 'shield-alert',
            'blocked_ip_updated' => 'pencil',
            'blocked_ip_deleted' => 'shield-check',
            'webhook_notification_redispatched' => 'refresh-cw',
            default => 'activity',
        };
    }

    protected static function colorClass(string $kind): string
    {
        return match (self::tone($kind)) {
            'success' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
            'info' => 'bg-sky-500/15 text-sky-700 dark:text-sky-400',
            'warning' => 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
            'danger' => 'bg-red-500/15 text-red-700 dark:text-red-400',
            default => 'bg-muted text-muted-foreground',
        };
    }

    protected static function tone(string $kind): string
    {
        return match ($kind) {
            'created', 'login', 'unbanned', 'restored', 'deletion_cancelled', 'setting_domain_created', 'plan_created',
            'subscription_assigned', 'subscription_reactivated', 'subscription_trial_converted',
            'ticket_created', 'ticket_category_created' => 'success',
            'updated', 'password_changed', 'password_reset', 'assigned', 'converted', 'merged',
            'setting_smtp', 'setting_domain_updated', 'setting_sender_updated', 'setting_test_email',
            'setting_policy_updated', 'plan_updated', 'subscription_upgraded',
            'ticket_updated', 'ticket_assigned', 'ticket_replied', 'ticket_category_updated' => 'info',
            'failed', 'deletion_requested', 'subscription_entered_grace' => 'warning',
            'deleted', 'force_deleted', 'purged', 'banned', 'setting_domain_deleted', 'plan_deleted',
            'subscription_cancelled', 'subscription_expired', 'ticket_category_deleted',
            'device_blocked', 'blocked_ip_created' => 'danger',
            'device_unblocked', 'device_revoked', 'blocked_ip_deleted' => 'warning',
            'blocked_ip_updated', 'webhook_notification_redispatched' => 'info',
            default => 'muted',
        };
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected static function title(string $kind, array $properties = []): string
    {
        if ($kind === 'setting_policy_updated') {
            return __('activity_logs.presenter.policy_updated', ['policy' => self::policyLabel($properties)]);
        }

        $key = "activity_logs.presenter.titles.{$kind}";
        $translation = __($key);

        return match ($kind) {
            'ticket_created' => __('tickets.activity.ticket_created'),
            'ticket_updated' => __('tickets.activity.ticket_updated'),
            'ticket_assigned' => __('tickets.activity.ticket_assigned'),
            'ticket_replied' => __('tickets.activity.ticket_replied'),
            'ticket_category_created' => __('ticket_categories.activity.created'),
            'ticket_category_updated' => __('ticket_categories.activity.updated'),
            'ticket_category_deleted' => __('ticket_categories.activity.deleted'),
            default => $translation === $key ? Str::headline($kind) : $translation,
        };
    }

    /**
     * Derives the human label for a `policy_{PolicyType::value}` area from
     * the enum itself, falling back to a headline of the raw slug for an
     * area that doesn't (or no longer) map to a case — old log rows must
     * keep rendering something sensible even if a policy type is retired.
     *
     * @param  array<string, mixed>  $properties
     */
    protected static function policyLabel(array $properties): string
    {
        $area = (string) ($properties['area'] ?? '');
        $value = Str::after($area, 'policy_');

        return PolicyType::tryFrom($value)?->label() ?? Str::headline($value);
    }

    /** Raw ISO 8601 string representing the datetime, converted client-side. */
    protected static function timestamp(?CarbonInterface $date): string
    {
        return $date ? $date->toIso8601String() : '—';
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<int, array{label: string, value: string|array<int, string>}>
     */
    protected static function rows(string $kind, Activity $activity, array $properties, ?Model $subject): array
    {
        $rows = [];

        // Self-evident for auth events — never worth a "Performed by" row.
        if (! in_array($kind, ['login', 'failed'], true)) {
            $performedByLabel = str_starts_with($kind, 'ticket_')
                ? __('tickets.activity.performed_by')
                : __('activity_logs.fields.performed_by');
            $rows[] = self::row($performedByLabel, self::performedBy($activity, $subject));
        }

        $rows = [...$rows, ...match ($kind) {
            'login' => [
                self::row(__('activity_logs.fields.device'), UserAgentParser::device($properties['user_agent'] ?? null)),
                self::row(__('activity_logs.fields.ip'), $properties['ip'] ?? null),
            ],
            'failed' => [
                self::row(__('activity_logs.fields.reason'), $properties['reason'] ?? __('activity_logs.values.invalid_credentials')),
                self::row(__('activity_logs.fields.ip'), $properties['ip'] ?? null),
            ],
            'banned' => [
                self::row(__('activity_logs.fields.reason'), $properties['ban_reason'] ?? null),
            ],
            'deletion_requested', 'deletion_cancelled', 'merged' => [
                self::row(__('activity_logs.fields.reason'), $properties['reason'] ?? null),
            ],
            'converted' => [
                self::row(__('activity_logs.fields.provider'), isset($properties['provider']) ? Str::headline((string) $properties['provider']) : null),
            ],
            'password_changed' => [
                self::row(__('activity_logs.fields.ip'), $properties['ip'] ?? null),
            ],
            'updated', 'plan_updated' => [
                self::row(__('activity_logs.fields.changed'), self::changedFields($properties)),
                self::row(__('activity_logs.fields.password'), ($properties['password_changed'] ?? false) ? __('activity_logs.values.changed') : null),
            ],
            'ticket_category_updated' => [
                self::row(__('ticket_categories.activity.changed'), self::changedTicketFields($properties, 'ticket_categories.fields')),
            ],
            'ticket_created' => [
                self::row(__('tickets.fields.category'), $properties['category'] ?? null),
                self::row(
                    __('tickets.fields.priority'),
                    isset($properties['priority'])
                        ? TicketPriority::tryFrom((string) $properties['priority'])?->label()
                        : null,
                ),
                self::row(__('tickets.fields.assigned_to'), $properties['agent'] ?? __('tickets.unassigned')),
            ],
            'ticket_updated' => [
                self::row(__('tickets.activity.changed'), self::changedTicketFields($properties, 'tickets.activity.fields')),
            ],
            'ticket_assigned' => [
                self::row(__('tickets.fields.agent'), $properties['agent'] ?? __('tickets.unassigned')),
            ],
            'setting_domain_created', 'setting_domain_updated', 'setting_domain_deleted' => [
                self::row(__('activity_logs.fields.domain'), $properties['domain'] ?? null),
            ],
            'setting_sender_updated' => [
                self::row(__('activity_logs.fields.purpose'), isset($properties['purpose']) ? Str::headline((string) $properties['purpose']) : null),
            ],
            'setting_test_email' => [
                self::row(__('activity_logs.fields.sent_to'), $properties['to'] ?? null),
            ],
            'setting_policy_updated' => [
                self::row(__('activity_logs.fields.version'), $properties['version'] ?? null),
            ],
            'subscription_assigned' => [
                self::row(__('activity_logs.fields.plan'), $properties['plan'] ?? null),
                self::row(__('activity_logs.fields.amount'), self::formatAmount($properties)),
                self::row(__('activity_logs.fields.provider'), isset($properties['provider']) ? Str::headline((string) $properties['provider']) : null),
            ],
            'subscription_upgraded' => [
                self::row(__('activity_logs.fields.from'), $properties['from_plan'] ?? null),
                self::row(__('activity_logs.fields.to'), $properties['to_plan'] ?? null),
                self::row(__('activity_logs.fields.credit_applied'), isset($properties['credit_applied']) && $properties['credit_applied'] > 0 ? self::formatAmount($properties, 'credit_applied') : null),
                self::row(__('activity_logs.fields.amount_charged'), self::formatAmount($properties, 'amount_charged')),
            ],
            'subscription_cancelled' => [
                self::row(__('activity_logs.fields.plan'), $properties['plan'] ?? null),
                self::row(__('activity_logs.fields.cancelled_by'), isset($properties['cancelled_by']) ? Str::headline((string) $properties['cancelled_by']) : null),
                self::row(__('activity_logs.fields.reason'), $properties['reason'] ?? null),
                self::row(__('activity_logs.fields.access'), ($properties['immediately'] ?? false) ? __('activity_logs.values.ended_immediately') : __('activity_logs.values.continues_until_period_end')),
            ],
            'subscription_reactivated' => [
                self::row(__('activity_logs.fields.plan'), $properties['plan'] ?? null),
            ],
            'subscription_trial_converted', 'subscription_entered_grace' => [
                self::row(__('activity_logs.fields.plan'), $properties['plan'] ?? null),
            ],
            'subscription_expired' => [
                self::row(__('activity_logs.fields.plan'), $properties['plan'] ?? null),
                self::row(__('activity_logs.fields.reason'), isset($properties['reason']) ? Str::headline((string) $properties['reason']) : null),
            ],
            'device_blocked' => [
                self::row(__('activity_logs.fields.device'), $properties['device_name'] ?? null),
                self::row(__('activity_logs.fields.reason'), $properties['reason'] ?? null),
            ],
            'device_unblocked', 'device_revoked' => [
                self::row(__('activity_logs.fields.device'), $properties['device_name'] ?? null),
            ],
            'blocked_ip_created', 'blocked_ip_updated' => [
                self::row(__('activity_logs.fields.ip_address'), $properties['ip_address'] ?? null),
                self::row(__('activity_logs.fields.scope'), isset($properties['scope']) ? Str::headline((string) $properties['scope']) : null),
                self::row(__('activity_logs.fields.reason'), $properties['reason'] ?? null),
            ],
            'blocked_ip_deleted' => [
                self::row(__('activity_logs.fields.ip_address'), $properties['ip_address'] ?? null),
            ],
            'webhook_notification_redispatched' => [
                self::row(__('activity_logs.fields.provider'), isset($properties['provider']) ? Str::headline((string) $properties['provider']) : null),
                self::row(__('activity_logs.fields.notification_type'), isset($properties['notification_type']) ? Str::headline((string) $properties['notification_type']) : null),
            ],
            default => [],
        }];

        return array_values(array_filter($rows));
    }

    /**
     * @param  string|array<int, string>|null  $value
     * @return array{label: string, value: string|array<int, string>}|null
     */
    protected static function row(string $label, string|array|null $value): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return ['label' => $label, 'value' => $value];
    }

    /** @param  array<string, mixed>  $properties */
    protected static function formatAmount(array $properties, string $key = 'amount'): ?string
    {
        if (! isset($properties[$key])) {
            return null;
        }

        $currency = $properties['currency'] ?? '';

        return trim("{$currency} ".number_format((float) $properties[$key], 2));
    }

    /** @return array<int, string> */
    protected static function changedFields(array $properties): array
    {
        return collect($properties['attributes'] ?? [])
            ->keys()
            ->map(fn (string $key): string => self::fieldLabel($key))
            ->all();
    }

    /** @return array<int, string> */
    protected static function changedTicketFields(array $properties, string $translationPrefix): array
    {
        return collect($properties['attributes'] ?? [])
            ->keys()
            ->map(function (string $key) use ($translationPrefix): string {
                $translation = __("{$translationPrefix}.{$key}");

                return $translation === "{$translationPrefix}.{$key}" ? Str::headline($key) : $translation;
            })
            ->all();
    }

    /**
     * "System" (no causer), "User" (the account acted on itself — e.g. a
     * self-service password change), "{name} (Admin)" for staff, else the
     * causer's plain name.
     */
    protected static function performedBy(Activity $activity, ?Model $subject = null): string
    {
        $causer = $activity->causer;

        if (! $causer instanceof User) {
            return __('activity_logs.values.system');
        }

        if ($subject instanceof User && $causer->is($subject)) {
            return __('activity_logs.values.user');
        }

        return $causer->isStaff()
            ? __('activity_logs.values.admin', ['name' => $causer->name])
            : $causer->name;
    }

    /**
     * The admin URL for an activity's subject, permission-gated — null when
     * there's no mapped destination (module has no detail/edit page, or the
     * subject class isn't registered below) or the viewer lacks the
     * permission to see it. Add one line to {@see subjectUrlResolvers()} for
     * a new subject type rather than growing an if/elseif at every call site.
     */
    public static function subjectUrl(?Model $subject): ?string
    {
        if (! $subject) {
            return null;
        }

        $resolver = static::subjectUrlResolvers()[$subject::class] ?? null;

        return $resolver ? $resolver($subject) : null;
    }

    /**
     * @return array<class-string, Closure(Model): ?string>
     */
    protected static function subjectUrlResolvers(): array
    {
        return [
            User::class => function (User $user): ?string {
                $viewer = auth()->user();

                return match (true) {
                    $user->isStaff() => $viewer->can('staff.edit') ? route('admin.staff.edit', $user) : null,
                    $user->isGuest() => $viewer->can('guests.manage') ? route('admin.guests.show', $user) : null,
                    default => $viewer->can('users.manage') ? route('admin.users.show', $user) : null,
                };
            },
            Plan::class => fn (Plan $plan): ?string => auth()->user()->can('plans.manage') ? route('admin.plans.show', $plan) : null,
            Ticket::class => fn (Ticket $ticket): ?string => auth()->user()->can('tickets.manage') ? route('admin.tickets.show', $ticket) : null,
            TicketCategory::class => fn (TicketCategory $category): ?string => auth()->user()->can('ticket_categories.edit') ? route('admin.ticket-categories.edit', $category) : null,
            Language::class => fn (Language $language): ?string => auth()->user()->can('languages.edit') ? route('admin.languages.edit', $language) : null,
            Notification::class => fn (Notification $notification): ?string => auth()->user()->can('notifications.edit') ? route('admin.notifications.edit', $notification) : null,
            Feedback::class => fn (Feedback $feedback): ?string => auth()->user()->can('feedback.manage') ? route('admin.feedback.show', $feedback) : null,
            Role::class => fn (Role $role): ?string => auth()->user()->can('roles.edit') ? route('admin.roles.edit', $role) : null,
            UserDevice::class => fn (UserDevice $device): ?string => auth()->user()->can('users.manage')
                ? route('admin.users.show', $device->user_id)
                : null,
            AppleNotification::class => fn (AppleNotification $notification): ?string => auth()->user()->can('webhook_notifications.view')
                ? route('admin.webhook-notifications.show', ['provider' => PaymentProvider::AppStore->value, 'id' => $notification->id])
                : null,
        ];
    }
}
