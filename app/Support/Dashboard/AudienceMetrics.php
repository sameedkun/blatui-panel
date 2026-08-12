<?php

namespace App\Support\Dashboard;

use App\Enum\ActivityAction;
use App\Enum\UserType;
use App\Models\User;
use App\Support\Dashboard\Concerns\BuildsTimeSeries;
use Illuminate\Support\Facades\DB;

/**
 * Who the accounts are and how the base is growing — the audience half of the dashboard.
 *
 * Every count here goes through the {@see User} model rather than a raw table query so
 * soft-deleted accounts stay excluded automatically; a trashed account is pending purge,
 * not part of the live base.
 */
class AudienceMetrics
{
    use BuildsTimeSeries;

    /** Live app users — the real product's end users. */
    public function totalUsers(): int
    {
        return User::appUsers()->count();
    }

    public function totalGuests(): int
    {
        return User::guests()->count();
    }

    public function totalStaff(): int
    {
        return User::staff()->count();
    }

    /** New app-user signups inside the window, with its change against the previous window. */
    public function newUsers(DateRange $range): array
    {
        $current = User::appUsers()
            ->whereBetween('created_at', [$range->start(), $range->end()])
            ->count();

        $previous = User::appUsers()
            ->whereBetween('created_at', [$range->previousStart(), $range->previousEnd()])
            ->count();

        return [
            'value' => $current,
            'previous' => $previous,
            'change' => $this->percentChange($current, $previous),
        ];
    }

    /**
     * Guests that became real app accounts inside the window.
     *
     * A conversion mutates the guest row in place (same id, `type` flips to `app`), so
     * there is no column that still says "this used to be a guest" — the audit log is the
     * only durable record of it, which is why this reads the activity trail rather than
     * the users table.
     *
     * Merges count too: a guest merged into an existing app account is still a guest that
     * stopped being one, and its row is force-deleted outright — so counting only
     * `converted` would quietly under-report the funnel.
     */
    public function conversions(DateRange $range): array
    {
        $count = fn ($from, $to): int => DB::table('activity_log')
            ->whereIn('event', [ActivityAction::Converted->value, ActivityAction::Merged->value])
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $current = $count($range->start(), $range->end());
        $previous = $count($range->previousStart(), $range->previousEnd());

        return [
            'value' => $current,
            'previous' => $previous,
            'change' => $this->percentChange($current, $previous),
        ];
    }

    /**
     * Signups plotted over the window, split by account type.
     *
     * @return array{labels: array<int, string>, users: array<int, int>, guests: array<int, int>}
     */
    public function signupSeries(DateRange $range): array
    {
        $users = $this->countByPeriod(User::appUsers(), $range);
        $guests = $this->countByPeriod(User::guests(), $range);

        return [
            'labels' => $this->bucketLabels($users, $range),
            'users' => array_values($users),
            'guests' => array_values($guests),
        ];
    }

    /**
     * App / Guest / Staff split for the audience donut.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function typeBreakdown(): array
    {
        $counts = User::query()
            ->groupBy('type')
            ->select(['type', DB::raw('COUNT(*) as aggregate')])
            ->pluck('aggregate', 'type')
            ->all();

        $labels = [];
        $values = [];

        // `enums.user_type` is keyed by the case name (TitleCase), not the backed value.
        foreach (UserType::cases() as $type) {
            $labels[] = __('enums.user_type.'.$type->name);
            $values[] = (int) ($counts[$type->value] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** Share of app users who have confirmed their email address. */
    public function verifiedRate(): float
    {
        $total = User::appUsers()->count();

        if ($total === 0) {
            return 0.0;
        }

        $verified = User::appUsers()->whereNotNull('email_verified_at')->count();

        return round(($verified / $total) * 100, 1);
    }

    public function bannedCount(): int
    {
        return User::banned()->count();
    }

    /** Accounts inside the deletion grace window, still recoverable. */
    public function pendingDeletionCount(): int
    {
        return User::pendingDeletion()->count();
    }

    /**
     * Where the audience actually is, by device-reported country.
     *
     * Country is only ever populated from client-supplied device data at registration
     * (no geo-IP package is installed), so rows without one are excluded rather than
     * bucketed as "unknown".
     *
     * @return array<int, array{country: string, code: ?string, total: int}>
     */
    public function topCountries(int $limit = 6): array
    {
        return DB::table('user_devices')
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->groupBy('country', 'country_code')
            ->select(['country', 'country_code as code', DB::raw('COUNT(DISTINCT user_id) as total')])
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'country' => $row->country,
                'code' => $row->code,
                'total' => (int) $row->total,
            ])
            ->all();
    }
}
