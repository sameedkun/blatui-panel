<?php

namespace App\Support\Dashboard\Metrics;

use App\Enum\CancelledBy;
use App\Enum\SubscriptionSource;
use App\Enum\SubscriptionStatus;
use App\Enum\TransactionType;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
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
 *
 * Everything here counts customers, so ownerless subscriptions (the account
 * was purged, the ledger kept — see DeletionService) are left out; their
 * money still counts in {@see RevenueMetrics}.
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
        return $this->customers()->where('status', $status->value)->count();
    }

    public function live(): int
    {
        return $this->customers()->whereIn('status', self::LIVE_STATUSES)->count();
    }

    public function started(DateRange $range): int
    {
        return $this->customers()->whereBetween('starts_at', [$range->start, $range->end])->count();
    }

    /** Cancellations in the window — every subscription, or only purchased ones (`$purchasedOnly`). */
    public function cancelled(DateRange $range, bool $purchasedOnly = false): int
    {
        return $this->cancellations()
            ->whereBetween('updated_at', [$range->start, $range->end])
            ->when($purchasedOnly, fn (Builder $query) => $query->where('source', SubscriptionSource::Purchase->value))
            ->count();
    }

    /** Subscriptions that lapsed in the window — every subscription, or only purchased ones (`$purchasedOnly`). */
    public function expired(DateRange $range, bool $purchasedOnly = false): int
    {
        return $this->customers()
            ->where('status', SubscriptionStatus::Expired->value)
            ->whereBetween('ends_at', [$range->start, $range->end])
            ->when($purchasedOnly, fn (Builder $query) => $query->where('source', SubscriptionSource::Purchase->value))
            ->count();
    }

    /** Paid subscriptions live at the start of the window — the churn denominator. */
    public function paidAt(CarbonInterface $at): int
    {
        return $this->revenue->liveCustomersAt($at)->count();
    }

    /**
     * Share of the paid subscriptions live at the window's start that were
     * cancelled or expired during it. Free grants are excluded on both sides —
     * a grant running out is not a paying customer leaving.
     */
    public function churnRate(DateRange $range): float
    {
        return Format::share($this->cancelled($range, true) + $this->expired($range, true), $this->paidAt($range->start));
    }

    /**
     * Renewals as a share of every period that came up for renewal in the
     * window (renewed plus lapsed).
     */
    public function renewalRate(DateRange $range): float
    {
        $renewals = $this->renewals($range);

        // Renewals are always paid, so the lapses they're measured against are too.
        return Format::share($renewals, $renewals + $this->expired($range, true));
    }

    /**
     * Of the trials that ended in the window, the share that became paying.
     * A subscription still mid-trial is neither a win nor a loss yet, so only
     * trials whose end date has passed are counted at all.
     */
    public function trialConversionRate(DateRange $range): float
    {
        $ended = $this->customers()
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
        $durations = $this->customers()
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
        return $this->customers()
            ->whereIn('status', self::LIVE_STATUSES)
            ->whereBetween('ends_at', [Date::now(), Date::now()->addDays($days)])
            ->where(fn (Builder $query) => $query->where('is_recurring', false)->orWhereNotNull('cancelled_by'))
            ->count();
    }

    public function renewals(DateRange $range): int
    {
        return SubscriptionTransaction::query()
            ->where('type', TransactionType::Renewal->value)
            ->whereBetween('purchased_at', [$range->start, $range->end])
            ->whereHas('subscription', fn (Builder $query) => $query->whereNotNull('user_id'))
            ->count();
    }

    /** @return array<string, int> status label => subscriptions, in lifecycle order */
    public function statusBreakdown(): array
    {
        $counts = $this->customers()
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
            ->withCount(['subscriptions as live_count' => fn (Builder $query) => $query->whereNotNull('user_id')->whereIn('status', self::LIVE_STATUSES)])
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
        return TimeSeries::count($this->customers(), $range, 'starts_at');
    }

    /** @return array<string, int> cancellations per bucket */
    public function cancelledSeries(DateRange $range): array
    {
        return TimeSeries::count($this->cancellations(), $range, 'updated_at');
    }

    /** @return array<string, int> expiries per bucket */
    public function expiredSeries(DateRange $range): array
    {
        return TimeSeries::count($this->customers()->where('status', SubscriptionStatus::Expired->value), $range, 'ends_at');
    }

    /**
     * Subscriptions a customer still owns.
     *
     * @return Builder<Subscription>
     */
    private function customers(): Builder
    {
        return Subscription::query()->whereNotNull('subscriptions.user_id');
    }

    /** @return Builder<Subscription> */
    private function cancellations(): Builder
    {
        return $this->customers()
            ->whereNotNull('cancelled_by')
            ->where('cancelled_by', '!=', CancelledBy::System->value);
    }
}
