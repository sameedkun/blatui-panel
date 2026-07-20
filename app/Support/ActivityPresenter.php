<?php

namespace App\Support;

use App\Enum\PolicyType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Turns one raw {@see Activity} row into display-ready data for the per-record
 * "Activity" tab timeline (shared by the Users and Guests profile pages). Pure
 * presentation only — it never writes activity, only reads it.
 */
class ActivityPresenter
{
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
            'created', 'login', 'unbanned', 'restored', 'deletion_cancelled', 'setting_domain_created' => 'success',
            'updated', 'password_changed', 'password_reset', 'assigned', 'converted', 'merged',
            'setting_smtp', 'setting_domain_updated', 'setting_sender_updated', 'setting_test_email',
            'setting_policy_updated' => 'info',
            'failed', 'deletion_requested' => 'warning',
            'deleted', 'force_deleted', 'purged', 'banned', 'setting_domain_deleted' => 'danger',
            default => 'muted',
        };
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected static function title(string $kind, array $properties = []): string
    {
        if ($kind === 'setting_policy_updated') {
            return self::policyLabel($properties).' Updated';
        }

        return match ($kind) {
            'created' => 'Account Created',
            'updated' => 'Profile Updated',
            'password_changed' => 'Password Changed',
            'deleted' => 'Account Deleted',
            'restored' => 'Account Restored',
            'force_deleted', 'purged' => 'Account Permanently Deleted',
            'banned' => 'Account Banned',
            'unbanned' => 'Account Unbanned',
            'assigned' => 'Role Assigned',
            'login' => 'Logged In',
            'failed' => 'Failed Login',
            'password_reset' => 'Password Reset',
            'deletion_requested' => 'Deletion Scheduled',
            'deletion_cancelled' => 'Deletion Cancelled',
            'converted' => 'Converted To App User',
            'merged' => 'Merged Into Another Account',
            'setting_smtp' => 'SMTP Settings Updated',
            'setting_domain_created' => 'Sending Domain Added',
            'setting_domain_updated' => 'Sending Domain Updated',
            'setting_domain_deleted' => 'Sending Domain Removed',
            'setting_sender_updated' => 'Mail Purpose Updated',
            'setting_test_email' => 'Test Email Sent',
            default => Str::headline($kind),
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

    /** "Today 4:13 PM" / "Yesterday" / "3 days ago", with the exact datetime in a tooltip. */
    protected static function timestamp(?CarbonInterface $date): string
    {
        if (! $date) {
            return '—';
        }

        return match (true) {
            $date->isToday() => 'Today '.$date->format('g:i A'),
            $date->isYesterday() => 'Yesterday',
            default => $date->diffForHumans(),
        };
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
            $rows[] = self::row('Performed by', self::performedBy($activity, $subject));
        }

        $rows = [...$rows, ...match ($kind) {
            'login' => [
                self::row('Device', UserAgentParser::device($properties['user_agent'] ?? null)),
                self::row('IP', $properties['ip'] ?? null),
            ],
            'failed' => [
                self::row('Reason', $properties['reason'] ?? 'Invalid credentials'),
                self::row('IP', $properties['ip'] ?? null),
            ],
            'banned' => [
                self::row('Reason', $properties['ban_reason'] ?? null),
            ],
            'deletion_requested', 'deletion_cancelled', 'merged' => [
                self::row('Reason', $properties['reason'] ?? null),
            ],
            'converted' => [
                self::row('Provider', isset($properties['provider']) ? Str::headline((string) $properties['provider']) : null),
            ],
            'password_changed' => [
                self::row('IP', $properties['ip'] ?? null),
            ],
            'updated' => [
                self::row('Changed', self::changedFields($properties)),
                self::row('Password', ($properties['password_changed'] ?? false) ? 'Changed' : null),
            ],
            'setting_domain_created', 'setting_domain_updated', 'setting_domain_deleted' => [
                self::row('Domain', $properties['domain'] ?? null),
            ],
            'setting_sender_updated' => [
                self::row('Purpose', isset($properties['purpose']) ? Str::headline((string) $properties['purpose']) : null),
            ],
            'setting_test_email' => [
                self::row('Sent to', $properties['to'] ?? null),
            ],
            'setting_policy_updated' => [
                self::row('Version', $properties['version'] ?? null),
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

    /** @return array<int, string> */
    protected static function changedFields(array $properties): array
    {
        return collect($properties['attributes'] ?? [])
            ->keys()
            ->map(fn (string $key): string => Str::headline($key))
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
            return 'System';
        }

        if ($subject instanceof User && $causer->is($subject)) {
            return 'User';
        }

        return $causer->isStaff() ? "{$causer->name} (Admin)" : $causer->name;
    }
}
