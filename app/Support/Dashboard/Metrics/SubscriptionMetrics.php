<?php

namespace App\Support\Dashboard\Metrics;

use App\Enum\CancelledBy;
use App\Enum\ReceiptType;
use App\Enum\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionReceipt;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\TimeSeries;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * The subscription lifecycle: how many are live, how many start, how many
 * leave, and how they move between states.
 *
 * There is no `cancelled_at` column — a cancellation is recognised by
 * `cancelled_by` being set, and dated by the row's `updated_at` (the
 * cancelling write). System cancellations are excluded: those are
 * SubscriptionService::subscribe() retiring a plan the user is replacing,
 * not a customer leaving. Expiry is dated by `ends_at`, which is exact.
 */
class SubscriptionMetrics
{
    /** Statuses that grant access right now — mirrors Subscription::isActive(). */
    public const array LIVE_STATUSES = [
        SubscriptionStatus::Trialing->value,
        SubscriptionStatus::Active->value,
        SubscriptionStatus::Grace->value,
    ];

    public function __construct(private readonly RevenueMetrics $revenue) {}

    public function countByStatus(SubscriptionStatus $status): int
    {
        return Subscription::query()->where('status', $status->value)->count();
    }

    public function live(): int
    {
        return Subscription::query()->whereIn('status', self::LIVE_STATUSES)->count();
    }

    public function started(DateRange $range): int
    {
        return Subscription::query()->whereBetween('starts_at', [$range->start, $range->end])->count();
    }

    public function cancelled(DateRange $range): int
    {
        return $this->cancellations()->whereBetween('updated_at', [$range->start, $range->end])->count();
    }

    public function expired(DateRange $range): int
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Expired->value)
            ->whereBetween('ends_at', [$range->start, $range->end])
            ->count();
    }

    /** Paid subscriptions live at the start of the window — the churn denominator. */
    public function paidAt(CarbonInterface $at): int
    {
        return $this->revenue->liveAt($at)->count();
    }

    /**
     * Share of the subscriptions live at the window's start that were
     * cancelled or expired during it.
     */
    public function churnRate(DateRange $range): float
    {
        return Format::share($this->cancelled($range) + $this->expired($range), $this->paidAt($range->start));
    }

    /**
     * Renewals as a share of every period that came up for renewal in the
     * window (renewed plus lapsed).
     */
    public function renewalRate(DateRange $range): float
    {
        $renewals = SubscriptionReceipt::query()
            ->where('type', ReceiptType::Renewal->value)
            ->whereBetween('created_at', [$range->start, $range->end])
            ->count();

        return Format::share($renewals, $renewals + $this->expired($range));
    }

    /**
     * Of the trials that ended in the window, the share that became paying.
     * A subscription still mid-trial is neither a win nor a loss yet, so only
     * trials whose end date has passed are counted at all.
     */
    public function trialConversionRate(DateRange $range): float
    {
        $ended = Subscription::query()
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [$range->start, min($range->end, Date::now())]);

        $total = (clone $ended)->count();
        $converted = $ended->whereNotIn('status', [
            SubscriptionStatus::Trialing->value,
            SubscriptionStatus::Expired->value,
            SubscriptionStatus::Failed->value,
        ])->count();

        return Format::share($converted, $total);
    }

    /** Average length in days of the subscriptions that ended during the window. */
    public function averageDurationDays(DateRange $range): ?float
    {
        $durations = Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Expired->value, SubscriptionStatus::Cancelled->value])
            ->whereBetween('ends_at', [$range->start, $range->end])
            ->toBase()
            ->get(['starts_at', 'ends_at'])
            ->map(fn (object $row): float => Date::parse($row->starts_at)->diffInSeconds(Date::parse($row->ends_at)) / 86400);

        return $durations->isEmpty() ? null : round($durations->avg(), 1);
    }

    /** Live subscriptions whose access ends within the next $days and won't renew on its own. */
    public function expiringSoon(int $days = 7): int
    {
        return Subscription::query()
            ->whereIn('status', self::LIVE_STATUSES)
            ->whereBetween('ends_at', [Date::now(), Date::now()->addDays($days)])
            ->where(fn (Builder $query) => $query->where('is_recurring', false)->orWhereNotNull('cancelled_by'))
            ->count();
    }

    public function renewals(DateRange $range): int
    {
        return SubscriptionReceipt::query()
            ->where('type', ReceiptType::Renewal->value)
            ->whereBetween('created_at', [$range->start, $range->end])
            ->count();
    }

    /** @return array<string, int> status label => subscriptions, in lifecycle order */
    public function statusBreakdown(): array
    {
        $counts = Subscription::query()
            ->groupBy('status')
            ->toBase()
            ->select(['status', DB::raw('COUNT(*) as aggregate')])
            ->pluck('aggregate', 'status');

        $breakdown = [];

        foreach (SubscriptionStatus::cases() as $status) {
            $breakdown[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $breakdown;
    }

    /** @return array<string, int> plan name => live subscriptions, most first */
    public function planDistribution(int $limit = 8): array
    {
        return Plan::query()
            ->withCount(['subscriptions as live_count' => fn (Builder $query) => $query->whereIn('status', self::LIVE_STATUSES)])
            ->orderByDesc('live_count')
            ->limit($limit)
            ->get()
            ->filter(fn (Plan $plan): bool => $plan->getAttribute('live_count') > 0)
            ->mapWithKeys(fn (Plan $plan): array => [$plan->name => (int) $plan->getAttribute('live_count')])
            ->all();
    }

    /** @return array<string, int> new subscriptions per bucket */
    public function startedSeries(DateRange $range): array
    {
        return TimeSeries::count(Subscription::query(), $range, 'starts_at');
    }

    /** @return array<string, int> cancellations per bucket */
    public function cancelledSeries(DateRange $range): array
    {
        return TimeSeries::count($this->cancellations(), $range, 'updated_at');
    }

    /** @return array<string, int> expiries per bucket */
    public function expiredSeries(DateRange $range): array
    {
        return TimeSeries::count(Subscription::query()->where('status', SubscriptionStatus::Expired->value), $range, 'ends_at');
    }

    private function cancellations(): Builder
    {
        return Subscription::query()
            ->whereNotNull('cancelled_by')
            ->where('cancelled_by', '!=', CancelledBy::System->value);
    }
}
