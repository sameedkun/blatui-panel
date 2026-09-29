<?php

namespace App\Support\Dashboard\Metrics;

use App\Enum\ActivityAction;
use App\Enum\ActivityLogName;
use App\Enum\DeviceType;
use App\Models\BlockedIp;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\TimeSeries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Application and account security — sign-ins, suspensions, blocked IPs and
 * device sessions. Deliberately not infrastructure: servers and networks
 * belong to the deployment's own monitoring, not to a product dashboard.
 *
 * Failed sign-ins come from the authentication audit trail, which
 * AuthActivityListener rate-limits per email+IP (5/min). The figure is
 * therefore "failed attempts worth recording", not raw request volume —
 * a credential-stuffing burst shows up as a spike, never as millions.
 */
class SecurityMetrics
{
    /** Audit events that make up the security event feed. */
    public const array SECURITY_EVENTS = [
        ActivityAction::Failed->value,
        ActivityAction::Banned->value,
        ActivityAction::Unbanned->value,
        ActivityAction::Blocked->value,
        ActivityAction::Unblocked->value,
        ActivityAction::Revoked->value,
        ActivityAction::PasswordReset->value,
    ];

    public function failedLogins(DateRange $range): int
    {
        return $this->auth(ActivityAction::Failed)->whereBetween('created_at', [$range->start, $range->end])->count();
    }

    public function successfulLogins(DateRange $range): int
    {
        return $this->auth(ActivityAction::Login)->whereBetween('created_at', [$range->start, $range->end])->count();
    }

    /** Sign-ins that used a passkey — the panel's phishing-resistant, MFA-grade factor. */
    public function passkeyLogins(DateRange $range): int
    {
        return $this->auth(ActivityAction::Login)
            ->where('properties->area', 'passkey')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->count();
    }

    public function passwordResets(DateRange $range): int
    {
        return $this->auth(ActivityAction::PasswordReset)->whereBetween('created_at', [$range->start, $range->end])->count();
    }

    /** Accounts whose password changed in the window, by any route (self-service, reset, admin). */
    public function passwordChanges(DateRange $range): int
    {
        return User::withTrashed()->whereBetween('password_changed_at', [$range->start, $range->end])->count();
    }

    public function suspensions(DateRange $range): int
    {
        return User::withTrashed()->whereBetween('banned_at', [$range->start, $range->end])->count();
    }

    public function currentlySuspended(): int
    {
        return User::query()->banned()->count();
    }

    public function activeBlockedIps(): int
    {
        return BlockedIp::query()->active()->count();
    }

    public function newBlockedIps(DateRange $range): int
    {
        return BlockedIp::query()->whereBetween('created_at', [$range->start, $range->end])->count();
    }

    /** Requests turned away by currently active blocks, over their lifetime. */
    public function blockedHits(): int
    {
        return (int) BlockedIp::query()->active()->sum('hits');
    }

    /** Devices currently holding a live API session. */
    public function activeSessions(): int
    {
        return UserDevice::query()->active()->count();
    }

    public function newDevices(DateRange $range): int
    {
        return UserDevice::query()->whereBetween('created_at', [$range->start, $range->end])->count();
    }

    public function revokedDevices(DateRange $range): int
    {
        return UserDevice::query()->whereBetween('revoked_at', [$range->start, $range->end])->count();
    }

    public function blockedDevices(DateRange $range): int
    {
        return UserDevice::query()->whereBetween('blocked_at', [$range->start, $range->end])->count();
    }

    /** @return array{success: array<string, int>, failed: array<string, int>} sign-ins per bucket */
    public function authSeries(DateRange $range): array
    {
        return [
            'success' => TimeSeries::count($this->auth(ActivityAction::Login), $range),
            'failed' => TimeSeries::count($this->auth(ActivityAction::Failed), $range),
        ];
    }

    /**
     * Buckets whose failed sign-ins ran well above the window's norm — at
     * least $minimum and $factor times the average bucket.
     *
     * @return array<string, int> bucket key => failed sign-ins
     */
    public function failedLoginSpikes(DateRange $range, float $factor = 3.0, int $minimum = 10): array
    {
        $series = $this->authSeries($range)['failed'];
        $average = count($series) > 0 ? array_sum($series) / count($series) : 0;

        return array_filter($series, fn (int $count): bool => $count >= $minimum && $count >= $average * $factor);
    }

    /** @return array<string, int> device type value => active devices */
    public function activeDevicesByType(): array
    {
        $counts = UserDevice::query()
            ->active()
            ->groupBy('device_type')
            ->toBase()
            ->select(['device_type', DB::raw('COUNT(*) as aggregate')])
            ->pluck('aggregate', 'device_type');

        $breakdown = [];

        foreach (DeviceType::cases() as $type) {
            $breakdown[$type->value] = (int) ($counts[$type->value] ?? 0);
        }

        return $breakdown;
    }

    /**
     * The latest security-relevant audit entries.
     *
     * @return Collection<int, Activity>
     */
    public function recentEvents(DateRange $range, int $limit = 8): Collection
    {
        return Activity::query()
            ->with('causer')
            ->whereIn('event', self::SECURITY_EVENTS)
            ->whereBetween('created_at', [$range->start, $range->end])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    private function auth(ActivityAction $action): Builder
    {
        return Activity::query()
            ->where('log_name', ActivityLogName::Authentication->value)
            ->where('event', $action->value);
    }
}
