<?php

namespace App\Support\Dashboard\Metrics;

use App\Enum\BillingInterval;
use App\Enum\PaymentProvider;
use App\Enum\ReceiptType;
use App\Enum\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionReceipt;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\TimeSeries;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Money coming into the platform.
 *
 * Revenue is `subscriptions.amount_paid`, attributed to `starts_at` — when the
 * period began, i.e. when it was paid for — so a backdated import lands on
 * the period it belongs to rather than the day it was entered. Store-billed
 * (webhook) contracts accumulate renewals and refunds into `amount_paid`, so
 * their totals are complete but a renewal is dated to the contract's start
 * (see ProviderSubscriptionService). Amounts are
 * summed as-is: a deployment selling in several currencies should normalise
 * `amount_paid` upstream (config('dashboard.currency') is the display currency).
 *
 * MRR is a point-in-time figure built from list prices, normalised to one
 * month, over subscriptions that were in a paid period at that moment.
 */
class RevenueMetrics
{
    public function revenue(DateRange $range): float
    {
        return round((float) $this->sold($range)->sum('amount_paid'), 2);
    }

    /** Paid subscription periods started in the window. */
    public function transactions(DateRange $range): int
    {
        return $this->sold($range)->where('amount_paid', '>', 0)->count();
    }

    /** Distinct customers who paid anything in the window. */
    public function payingCustomers(DateRange $range): int
    {
        return (int) $this->sold($range)->where('amount_paid', '>', 0)->distinct()->count('user_id');
    }

    /** Average revenue per paying customer in the window. */
    public function arpu(DateRange $range): float
    {
        $customers = $this->payingCustomers($range);

        return $customers === 0 ? 0.0 : round($this->revenue($range) / $customers, 2);
    }

    public function averageOrderValue(DateRange $range): float
    {
        $transactions = $this->transactions($range);

        return $transactions === 0 ? 0.0 : round($this->revenue($range) / $transactions, 2);
    }

    /**
     * Refund receipts recorded in the window, and the value of the periods
     * they refunded.
     *
     * @return array{count: int, amount: float}
     */
    public function refunds(DateRange $range): array
    {
        $refunds = SubscriptionReceipt::query()
            ->where('type', ReceiptType::Refund->value)
            ->whereBetween('subscription_receipts.created_at', [$range->start, $range->end]);

        return [
            'count' => (clone $refunds)->count(),
            'amount' => round((float) $refunds
                ->join('subscriptions', 'subscriptions.id', '=', 'subscription_receipts.subscription_id')
                ->sum('subscriptions.amount_paid'), 2),
        ];
    }

    /** Monthly recurring revenue at a moment (now by default). */
    public function mrr(?CarbonInterface $at = null): float
    {
        $at ??= Date::now();

        $rows = $this->liveAt($at)
            ->join('plan_prices', 'plan_prices.id', '=', 'subscriptions.plan_price_id')
            ->groupBy('plan_prices.billing_interval', 'plan_prices.billing_period')
            ->select([
                'plan_prices.billing_interval',
                'plan_prices.billing_period',
                DB::raw('SUM(plan_prices.amount) as amount'),
            ])
            ->toBase()
            ->get();

        return round($rows->sum(fn (object $row): float => (float) $row->amount * self::monthlyFactor(
            BillingInterval::tryFrom((string) $row->billing_interval) ?? BillingInterval::Month,
            (int) $row->billing_period,
        )), 2);
    }

    public function arr(?CarbonInterface $at = null): float
    {
        return round($this->mrr($at) * 12, 2);
    }

    /**
     * Monthly-normalised list price of the subscriptions that started (new
     * MRR) or ended (churned MRR) in the window.
     *
     * @return array{new: float, churned: float, net: float}
     */
    public function mrrMovement(DateRange $range): array
    {
        $normalised = fn (Builder $query): float => round($query
            ->join('plan_prices', 'plan_prices.id', '=', 'subscriptions.plan_price_id')
            ->where('subscriptions.status', '!=', SubscriptionStatus::Failed->value)
            ->groupBy('plan_prices.billing_interval', 'plan_prices.billing_period')
            ->select(['plan_prices.billing_interval', 'plan_prices.billing_period', DB::raw('SUM(plan_prices.amount) as amount')])
            ->toBase()
            ->get()
            ->sum(fn (object $row): float => (float) $row->amount * self::monthlyFactor(
                BillingInterval::tryFrom((string) $row->billing_interval) ?? BillingInterval::Month,
                (int) $row->billing_period,
            )), 2);

        $new = $normalised(Subscription::query()
            ->where('subscriptions.amount_paid', '>', 0)
            ->whereBetween('subscriptions.starts_at', [$range->start, $range->end]));

        $churned = $normalised(Subscription::query()
            ->whereIn('subscriptions.status', [SubscriptionStatus::Expired->value, SubscriptionStatus::Cancelled->value])
            ->whereBetween('subscriptions.ends_at', [$range->start, $range->end]));

        return ['new' => $new, 'churned' => $churned, 'net' => round($new - $churned, 2)];
    }

    /** @return array<string, float> revenue per bucket */
    public function revenueSeries(DateRange $range): array
    {
        return TimeSeries::sum(Subscription::query(), $range, 'amount_paid', 'starts_at');
    }

    /** @return array<string, float> plan name => revenue, highest first */
    public function revenueByPlan(DateRange $range, int $limit = 8): array
    {
        return $this->sold($range)
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->groupBy('plans.id', 'plans.name')
            ->orderByDesc('amount')
            ->limit($limit)
            ->toBase()
            ->select(['plans.name', DB::raw('SUM(subscriptions.amount_paid) as amount')])
            ->pluck('amount', 'name')
            ->map(fn ($amount): float => round((float) $amount, 2))
            ->all();
    }

    /** @return array<string, float> billing cycle label => revenue, highest first */
    public function revenueByBilling(DateRange $range): array
    {
        return $this->sold($range)
            ->join('plan_prices', 'plan_prices.id', '=', 'subscriptions.plan_price_id')
            ->groupBy('plan_prices.billing_interval', 'plan_prices.billing_period')
            ->toBase()
            ->select(['plan_prices.billing_interval', 'plan_prices.billing_period', DB::raw('SUM(subscriptions.amount_paid) as amount')])
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                self::billingLabel(BillingInterval::tryFrom((string) $row->billing_interval) ?? BillingInterval::Month, (int) $row->billing_period) => round((float) $row->amount, 2),
            ])
            ->sortDesc()
            ->all();
    }

    /** @return array<string, float> provider label => revenue, highest first */
    public function revenueByProvider(DateRange $range): array
    {
        return $this->sold($range)
            ->groupBy('provider')
            ->toBase()
            ->select(['provider', DB::raw('SUM(amount_paid) as amount')])
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (PaymentProvider::tryFrom((string) $row->provider)?->label() ?? (string) $row->provider) => round((float) $row->amount, 2),
            ])
            ->sortDesc()
            ->all();
    }

    /**
     * What happened to the window's subscription starts.
     *
     * @return array{paid: int, trials: int, failed: int, refunded: int}
     */
    public function transactionOutcomes(DateRange $range): array
    {
        return [
            'paid' => $this->transactions($range),
            'trials' => $this->sold($range)->where('status', SubscriptionStatus::Trialing->value)->count(),
            'failed' => $this->sold($range)->where('status', SubscriptionStatus::Failed->value)->count(),
            'refunded' => $this->refunds($range)['count'],
        ];
    }

    /**
     * Subscriptions in a paid period at a moment: started, not yet ended,
     * past any trial, and not a failed payment. Time-based rather than
     * status-based so it answers historical moments too — `status` only
     * holds a subscription's *current* state.
     */
    public function liveAt(CarbonInterface $at): Builder
    {
        return Subscription::query()
            ->where('subscriptions.status', '!=', SubscriptionStatus::Failed->value)
            ->where('subscriptions.starts_at', '<=', $at)
            ->where(fn (Builder $query) => $query->whereNull('subscriptions.ends_at')->orWhere('subscriptions.ends_at', '>', $at))
            ->where(fn (Builder $query) => $query->whereNull('subscriptions.trial_ends_at')->orWhere('subscriptions.trial_ends_at', '<=', $at));
    }

    /** Multiplier turning one billing cycle's price into a monthly amount. */
    public static function monthlyFactor(BillingInterval $interval, int $period): float
    {
        $period = max(1, $period);

        return match ($interval) {
            BillingInterval::Day => 30 / $period,
            BillingInterval::Week => (52 / 12) / $period,
            BillingInterval::Month => 1 / $period,
            BillingInterval::Year => 1 / (12 * $period),
        };
    }

    public static function billingLabel(BillingInterval $interval, int $period): string
    {
        return $period <= 1
            ? __('dashboard.billing.'.$interval->value)
            : __('dashboard.billing.every', ['period' => trans_choice('enums.billing_interval_count.'.$interval->name, $period, ['count' => $period])]);
    }

    /** Subscriptions whose paid period started in the window. */
    private function sold(DateRange $range): Builder
    {
        return Subscription::query()->whereBetween('subscriptions.starts_at', [$range->start, $range->end]);
    }
}
