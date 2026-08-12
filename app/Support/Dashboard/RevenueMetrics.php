<?php

namespace App\Support\Dashboard;

use App\Enum\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Dashboard\Concerns\BuildsTimeSeries;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Subscription and billing analytics.
 *
 * "Live" here means the same thing it means everywhere else in the app — the statuses
 * {@see Subscription::isActive()} treats as granting access. It is spelled out as a
 * constant rather than re-listed per query so a future status can't be added to some
 * counts and forgotten in others.
 */
class RevenueMetrics
{
    use BuildsTimeSeries;

    /** Statuses that count as a subscriber having access right now. */
    public const LIVE_STATUSES = [
        SubscriptionStatus::Trialing->value,
        SubscriptionStatus::Active->value,
        SubscriptionStatus::Grace->value,
    ];

    public function activeSubscriptions(): int
    {
        return Subscription::query()->whereIn('status', self::LIVE_STATUSES)->count();
    }

    /** Revenue collected inside the window, with its change against the previous window. */
    public function revenue(DateRange $range): array
    {
        $sum = fn ($from, $to): float => (float) Subscription::query()
            ->whereBetween('starts_at', [$from, $to])
            ->sum('amount_paid');

        $current = $sum($range->start(), $range->end());
        $previous = $sum($range->previousStart(), $range->previousEnd());

        return [
            'value' => $current,
            'previous' => $previous,
            'change' => $this->percentChange($current, $previous),
        ];
    }

    public function lifetimeRevenue(): float
    {
        return (float) Subscription::query()->sum('amount_paid');
    }

    /**
     * Average revenue per paying subscriber, over all time.
     *
     * Divides by subscribers rather than subscriptions — one user upgrading three times
     * is one customer, and counting them three times would deflate the figure.
     */
    public function averageRevenuePerUser(): float
    {
        $subscribers = Subscription::query()->distinct('user_id')->count('user_id');

        if ($subscribers === 0) {
            return 0.0;
        }

        return round($this->lifetimeRevenue() / $subscribers, 2);
    }

    /**
     * Revenue plotted over the window.
     *
     * Bucketed on `starts_at` (when the subscription period began, i.e. when it was paid
     * for) rather than `created_at`, so a backdated import lands on the period it actually
     * belongs to.
     *
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    public function revenueSeries(DateRange $range): array
    {
        $buckets = $this->sumByPeriod(Subscription::query(), $range, 'amount_paid', 'starts_at');

        return [
            'labels' => $this->bucketLabels($buckets, $range),
            'values' => array_values($buckets),
        ];
    }

    /**
     * New vs cancelled subscriptions over the window — the churn picture.
     *
     * @return array{labels: array<int, string>, new: array<int, int>, cancelled: array<int, int>}
     */
    public function churnSeries(DateRange $range): array
    {
        $new = $this->countByPeriod(Subscription::query(), $range, 'starts_at');
        $cancelled = $this->countByPeriod(
            Subscription::query()->where('status', SubscriptionStatus::Cancelled->value),
            $range,
            'updated_at',
        );

        return [
            'labels' => $this->bucketLabels($new, $range),
            'new' => array_values($new),
            'cancelled' => array_values($cancelled),
        ];
    }

    /**
     * Every status and how many subscriptions sit in it.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function statusBreakdown(): array
    {
        $counts = Subscription::query()
            ->groupBy('status')
            ->select(['status', DB::raw('COUNT(*) as aggregate')])
            ->pluck('aggregate', 'status')
            ->all();

        $labels = [];
        $values = [];

        foreach (SubscriptionStatus::cases() as $status) {
            $labels[] = $status->label();
            $values[] = (int) ($counts[$status->value] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Live subscribers per plan, for the plan-distribution bar.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function planDistribution(): array
    {
        $rows = Plan::query()
            ->withCount(['subscriptions as live_count' => fn ($q) => $q->whereIn('status', self::LIVE_STATUSES)])
            ->orderByDesc('live_count')
            ->get();

        return [
            'labels' => $rows->pluck('name')->all(),
            'values' => $rows->pluck('live_count')->map(fn ($v): int => (int) $v)->all(),
        ];
    }

    /**
     * Share of trials that went on to become paying subscriptions.
     *
     * A trial counts as converted once it has left `trialing` for anything that is not an
     * outright expiry — a subscription still mid-trial is neither a win nor a loss yet, so
     * counting it either way would move the rate around purely on timing.
     */
    public function trialConversionRate(): float
    {
        $everTrialed = Subscription::query()->whereNotNull('trial_ends_at')->count();

        if ($everTrialed === 0) {
            return 0.0;
        }

        $converted = Subscription::query()
            ->whereNotNull('trial_ends_at')
            ->whereNotIn('status', [
                SubscriptionStatus::Trialing->value,
                SubscriptionStatus::Expired->value,
                SubscriptionStatus::Failed->value,
            ])
            ->count();

        return round(($converted / $everTrialed) * 100, 1);
    }

    /**
     * The most recent subscriptions sold, for the billing table.
     *
     * @return Collection<int, Subscription>
     */
    public function recentSubscriptions(int $limit = 6)
    {
        return Subscription::query()
            ->with(['user', 'plan'])
            ->latest('starts_at')
            ->limit($limit)
            ->get();
    }
}
