<?php

namespace App\Support\Dashboard\Metrics;

use App\Enum\BillingInterval;
use App\Enum\PaymentProvider;
use App\Enum\SubscriptionSource;
use App\Enum\SubscriptionStatus;
use App\Enum\TransactionType;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\TimeSeries;
use App\Support\Money\Currency;
use App\Support\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Money coming into the platform, read from the transaction ledger
 * (`subscription_transactions`) — never from subscriptions or plan prices.
 *
 * "Revenue" here is gross sales: what customers were charged (Initial,
 * Renewal and PlanChange transactions), as reported by the provider —
 * tax-inclusive and before store commission, so not proceeds. Refunds are
 * reported separately. Every amount is dated by when the money moved
 * (`purchased_at`), so a renewal counts in the month it was charged.
 *
 * Currencies are never added together. Every money figure is computed in the
 * reporting currency (`config('dashboard.currency')`) from the transactions
 * charged in that currency; sales in other currencies are exact and visible
 * through {@see self::salesByCurrency()}, and every figure that leaves them
 * out can say how much it left out ({@see self::otherCurrencies()},
 * {@see self::mrrOtherCurrencies()}). A future FX layer can store a
 * reporting-currency value on each transaction and have these sums read it,
 * without changing any caller. Counts take an optional currency: shown next
 * to a money figure they use the same currency; on their own they span all.
 *
 * Two different questions are answered here and never mixed:
 *   - **Cash collected** — revenue/refunds/ARPU/AOV/series: what moved in a
 *     window, dated by `purchased_at`.
 *   - **Recurring value** — MRR/ARR: what the paying base is worth per
 *     month/year at a moment, not what was collected.
 *
 * MRR at a moment = for every *purchased* subscription in a paid period
 * ({@see self::liveAt()}: started, not ended, past its trial, not failed),
 * its most recent charge by `purchased_at` up to that moment, divided by the
 * billing periods that charge covers (`periods_covered` — a 3-month
 * pay-up-front offer counts a third per month), normalised to one month by
 * the price's billing cycle. Consequences, by design:
 *   - grants and trials add nothing (no charge, or not yet in a paid period);
 *   - an introductory/discounted price counts at what was actually charged
 *     until a later charge replaces it; a price change counts from its first
 *     charge — never from the current list price;
 *   - a contract with auto-renew off counts until its paid period ends;
 *     one in billing grace (period over, payment pending) doesn't;
 *   - amounts are gross (tax-inclusive, before store fees), like revenue;
 *   - only charges in the reporting currency count — the rest are reported
 *     as left out, never as zero.
 * ARR = MRR × 12: the annualised run rate of the current paying base, not
 * trailing-twelve-month revenue.
 */
class RevenueMetrics
{
    /** The currency every money figure is reported in. */
    public function currency(): string
    {
        return Currency::normalize((string) config('dashboard.currency', 'USD'));
    }

    /** Gross sales in the reporting currency. */
    public function revenue(DateRange $range): float
    {
        return $this->major($this->sales($range)->sum('subscription_transactions.amount_minor'));
    }

    /** Paid charges in the window — every currency, or only `$currency`. */
    public function transactions(DateRange $range, ?string $currency = null): int
    {
        return $this->charges($range)
            ->where('subscription_transactions.amount_minor', '>', 0)
            ->when($currency, fn (Builder $query, string $currency) => $query->where('subscription_transactions.currency', Currency::normalize($currency)))
            ->count();
    }

    /**
     * Distinct customers charged anything in the window — every currency, or
     * only `$currency`. Ownerless charges (account purged) have no customer.
     */
    public function payingCustomers(DateRange $range, ?string $currency = null): int
    {
        return (int) $this->charges($range)
            ->where('subscription_transactions.amount_minor', '>', 0)
            ->when($currency, fn (Builder $query, string $currency) => $query->where('subscription_transactions.currency', $currency))
            ->join('subscriptions', 'subscriptions.id', '=', 'subscription_transactions.subscription_id')
            ->whereNotNull('subscriptions.user_id')
            ->distinct()
            ->count('subscriptions.user_id');
    }

    /**
     * Gross sales per customer charged in the reporting currency — customers'
     * money only, so ownerless charges don't inflate it.
     */
    public function arpu(DateRange $range): float
    {
        $customers = $this->payingCustomers($range, $this->currency());

        if ($customers === 0) {
            return 0.0;
        }

        $customerSales = $this->sales($range)
            ->whereHas('subscription', fn (Builder $query) => $query->whereNotNull('user_id'))
            ->sum('subscription_transactions.amount_minor');

        return round($this->major($customerSales) / $customers, 2);
    }

    /** Average charge in the reporting currency. */
    public function averageOrderValue(DateRange $range): float
    {
        $charges = $this->sales($range)->where('subscription_transactions.amount_minor', '>', 0)->count();

        return $charges === 0 ? 0.0 : round($this->revenue($range) / $charges, 2);
    }

    /**
     * Refunds issued in the window, in the reporting currency: how many and
     * how much (less any the provider reversed).
     *
     * @return array{count: int, amount: float}
     */
    public function refunds(DateRange $range): array
    {
        $refunds = $this->refundRows($range)->where('subscription_transactions.currency', $this->currency());

        $amount = $this->ledger($range)
            ->where('subscription_transactions.currency', $this->currency())
            ->whereIn('subscription_transactions.type', [TransactionType::Refund->value, TransactionType::RefundReversed->value])
            ->sum(DB::raw('-('.SubscriptionTransaction::signedAmountSql().')'));

        return [
            'count' => $refunds->count(),
            'amount' => $this->major($amount),
        ];
    }

    /**
     * Exact sales per currency in the window — no conversion. Reporting
     * currency first, then by number of charges.
     *
     * @return array<string, array{gross: Money, refunds: Money, net: Money, transactions: int}>
     */
    public function salesByCurrency(DateRange $range): array
    {
        $charges = "'".implode("','", TransactionType::chargeValues())."'";
        $refund = TransactionType::Refund->value;
        $reversed = TransactionType::RefundReversed->value;

        $rows = $this->ledger($range)
            ->groupBy('subscription_transactions.currency')
            ->toBase()
            ->select('subscription_transactions.currency')
            ->selectRaw("SUM(CASE WHEN subscription_transactions.type IN ({$charges}) THEN subscription_transactions.amount_minor ELSE 0 END) as gross")
            ->selectRaw("SUM(CASE WHEN subscription_transactions.type = '{$refund}' THEN subscription_transactions.amount_minor WHEN subscription_transactions.type = '{$reversed}' THEN -subscription_transactions.amount_minor ELSE 0 END) as refunded")
            ->selectRaw("SUM(CASE WHEN subscription_transactions.type IN ({$charges}) AND subscription_transactions.amount_minor > 0 THEN 1 ELSE 0 END) as charges")
            ->get();

        $reporting = $this->currency();

        return $rows
            ->sortBy([
                fn (object $a, object $b): int => ($b->currency === $reporting) <=> ($a->currency === $reporting),
                fn (object $a, object $b): int => (int) $b->charges <=> (int) $a->charges,
            ])
            ->mapWithKeys(fn (object $row): array => [(string) $row->currency => [
                'gross' => Money::ofMinor((int) $row->gross, (string) $row->currency),
                'refunds' => Money::ofMinor((int) $row->refunded, (string) $row->currency),
                'net' => Money::ofMinor((int) $row->gross - (int) $row->refunded, (string) $row->currency),
                'transactions' => (int) $row->charges,
            ]])
            ->all();
    }

    /** Currencies other than the reporting one that had sales in the window — what the headline figures leave out. */
    public function otherCurrencies(DateRange $range): int
    {
        return $this->charges($range)
            ->where('subscription_transactions.currency', '!=', $this->currency())
            ->distinct()
            ->count('subscription_transactions.currency');
    }

    /** Monthly recurring revenue at a moment (now by default), in the reporting currency — see the class doc. */
    public function mrr(?CarbonInterface $at = null): float
    {
        $at ??= Date::now();

        return $this->monthlyValue($this->liveAt($at), $at);
    }

    /** Paying subscriptions MRR leaves out because their latest charge is in another currency. */
    public function mrrOtherCurrencies(?CarbonInterface $at = null): int
    {
        $at ??= Date::now();

        return $this->withLatestCharge($this->liveAt($at), $at)
            ->where('latest_charge.currency', '!=', $this->currency())
            ->count();
    }

    public function arr(?CarbonInterface $at = null): float
    {
        return round($this->mrr($at) * 12, 2);
    }

    /**
     * Monthly-normalised latest charge of the subscriptions that started
     * (new MRR) or ended (churned MRR) in the window.
     *
     * @return array{new: float, churned: float, net: float}
     */
    public function mrrMovement(DateRange $range): array
    {
        $new = $this->monthlyValue(Subscription::query()
            ->where('subscriptions.source', SubscriptionSource::Purchase->value)
            ->where('subscriptions.status', '!=', SubscriptionStatus::Failed->value)
            ->whereBetween('subscriptions.starts_at', [$range->start, $range->end]));

        $churned = $this->monthlyValue(Subscription::query()
            ->whereIn('subscriptions.status', [SubscriptionStatus::Expired->value, SubscriptionStatus::Cancelled->value])
            ->whereBetween('subscriptions.ends_at', [$range->start, $range->end]));

        return ['new' => $new, 'churned' => $churned, 'net' => round($new - $churned, 2)];
    }

    /** @return array<string, float> gross sales per bucket, reporting currency */
    public function revenueSeries(DateRange $range): array
    {
        return array_map(
            fn (float $minor): float => $this->major($minor),
            TimeSeries::sum($this->sales($range), $range, 'subscription_transactions.amount_minor', 'subscription_transactions.purchased_at'),
        );
    }

    /** @return array<string, float> plan name => gross sales, highest first */
    public function revenueByPlan(DateRange $range, int $limit = 8): array
    {
        return $this->sales($range)
            ->join('subscriptions', 'subscriptions.id', '=', 'subscription_transactions.subscription_id')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->groupBy('plans.id', 'plans.name')
            ->orderByDesc('amount')
            ->limit($limit)
            ->toBase()
            ->select(['plans.name', DB::raw('SUM(subscription_transactions.amount_minor) as amount')])
            ->pluck('amount', 'name')
            ->map(fn ($amount): float => $this->major($amount))
            ->all();
    }

    /** @return array<int, float> plan id => gross sales, every plan with sales */
    public function revenueByPlanId(DateRange $range): array
    {
        return $this->sales($range)
            ->join('subscriptions', 'subscriptions.id', '=', 'subscription_transactions.subscription_id')
            ->groupBy('subscriptions.plan_id')
            ->toBase()
            ->select(['subscriptions.plan_id', DB::raw('SUM(subscription_transactions.amount_minor) as amount')])
            ->pluck('amount', 'plan_id')
            ->map(fn ($amount): float => $this->major($amount))
            ->all();
    }

    /** @return array<string, float> billing cycle label => gross sales, highest first */
    public function revenueByBilling(DateRange $range): array
    {
        return $this->sales($range)
            ->join('subscriptions', 'subscriptions.id', '=', 'subscription_transactions.subscription_id')
            ->join('plan_prices', 'plan_prices.id', '=', 'subscriptions.plan_price_id')
            ->groupBy('plan_prices.billing_interval', 'plan_prices.billing_period')
            ->toBase()
            ->select(['plan_prices.billing_interval', 'plan_prices.billing_period', DB::raw('SUM(subscription_transactions.amount_minor) as amount')])
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                self::billingLabel(BillingInterval::tryFrom((string) $row->billing_interval) ?? BillingInterval::Month, (int) $row->billing_period) => $this->major($row->amount),
            ])
            ->sortDesc()
            ->all();
    }

    /** @return array<string, float> provider label => gross sales, highest first */
    public function revenueByProvider(DateRange $range): array
    {
        return $this->sales($range)
            ->groupBy('subscription_transactions.provider')
            ->toBase()
            ->select(['subscription_transactions.provider', DB::raw('SUM(subscription_transactions.amount_minor) as amount')])
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (PaymentProvider::tryFrom((string) $row->provider)?->label() ?? (string) $row->provider) => $this->major($row->amount),
            ])
            ->sortDesc()
            ->all();
    }

    /**
     * What happened in the window: paid charges and refunds from the ledger,
     * trials started and failed payments from the subscriptions that began.
     *
     * @return array{paid: int, trials: int, failed: int, refunded: int}
     */
    public function transactionOutcomes(DateRange $range): array
    {
        $started = fn (): Builder => Subscription::query()->whereBetween('subscriptions.starts_at', [$range->start, $range->end]);

        return [
            'paid' => $this->transactions($range),
            'trials' => $started()->where('status', SubscriptionStatus::Trialing->value)->count(),
            'failed' => $started()->where('status', SubscriptionStatus::Failed->value)->count(),
            'refunded' => $this->refundRows($range)->count(),
        ];
    }

    /**
     * Purchased subscriptions in a paid period at a moment: started, not yet
     * ended, past any trial, and not a failed payment. Free grants are never
     * "paying". Time-based rather than status-based so it answers historical
     * moments too — `status` only holds a subscription's *current* state.
     *
     * @return Builder<Subscription>
     */
    public function liveAt(CarbonInterface $at): Builder
    {
        return Subscription::query()
            ->where('subscriptions.source', SubscriptionSource::Purchase->value)
            ->where('subscriptions.status', '!=', SubscriptionStatus::Failed->value)
            ->where('subscriptions.starts_at', '<=', $at)
            ->where(fn (Builder $query) => $query->whereNull('subscriptions.ends_at')->orWhere('subscriptions.ends_at', '>', $at))
            ->where(fn (Builder $query) => $query->whereNull('subscriptions.trial_ends_at')->orWhere('subscriptions.trial_ends_at', '<=', $at));
    }

    /**
     * {@see liveAt()} limited to subscriptions a customer still owns — the base
     * of customer counts (paying subscribers, churn). Ownerless contracts
     * (account purged, store still billing) stay in {@see liveAt()} and so in
     * MRR, since that money is real.
     *
     * @return Builder<Subscription>
     */
    public function liveCustomersAt(CarbonInterface $at): Builder
    {
        return $this->liveAt($at)->whereNotNull('subscriptions.user_id');
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

    /**
     * Each subscription's most recent charge (up to `$at`) in the reporting
     * currency, per billing period it covers, normalised to a month by its
     * billing cycle, summed.
     *
     * @param  Builder<Subscription>  $subscriptions
     */
    private function monthlyValue(Builder $subscriptions, ?CarbonInterface $at = null): float
    {
        $rows = $this->withLatestCharge($subscriptions, $at)
            ->join('plan_prices', 'plan_prices.id', '=', 'subscriptions.plan_price_id')
            ->where('latest_charge.currency', $this->currency())
            ->groupBy('plan_prices.billing_interval', 'plan_prices.billing_period')
            ->select([
                'plan_prices.billing_interval',
                'plan_prices.billing_period',
                DB::raw('SUM(latest_charge.amount_minor * 1.0 / latest_charge.periods_covered) as amount'),
            ])
            ->get();

        return round($rows->sum(fn (object $row): float => $this->major($row->amount) * self::monthlyFactor(
            BillingInterval::tryFrom((string) $row->billing_interval) ?? BillingInterval::Month,
            (int) $row->billing_period,
        )), 2);
    }

    /**
     * The subscriptions joined to their latest charge (`latest_charge`): the
     * priced charge with the latest `purchased_at` up to `$at`, ties broken by
     * id — not the last one inserted, since a late or reprocessed delivery can
     * insert an older charge after a newer one.
     *
     * @param  Builder<Subscription>  $subscriptions
     */
    private function withLatestCharge(Builder $subscriptions, ?CarbonInterface $at): QueryBuilder
    {
        return $subscriptions
            ->toBase()
            ->join('subscription_transactions as latest_charge', fn (JoinClause $join) => $join->where('latest_charge.id', '=', fn ($query) => $query
                ->select('charge.id')
                ->from('subscription_transactions as charge')
                ->whereColumn('charge.subscription_id', 'subscriptions.id')
                ->whereIn('charge.type', TransactionType::chargeValues())
                ->whereNotNull('charge.amount_minor')
                ->when($at, fn ($query, CarbonInterface $at) => $query->where('charge.purchased_at', '<=', $at))
                ->orderByDesc('charge.purchased_at')
                ->orderByDesc('charge.id')
                ->limit(1)));
    }

    /**
     * Refund rows dated inside the window, any currency.
     *
     * @return Builder<SubscriptionTransaction>
     */
    private function refundRows(DateRange $range): Builder
    {
        return $this->ledger($range)->where('subscription_transactions.type', TransactionType::Refund->value);
    }

    /**
     * Priced ledger rows dated inside the window.
     *
     * @return Builder<SubscriptionTransaction>
     */
    private function ledger(DateRange $range): Builder
    {
        return SubscriptionTransaction::query()
            ->priced()
            ->whereBetween('subscription_transactions.purchased_at', [$range->start, $range->end]);
    }

    /**
     * Customer charges in the window, any currency.
     *
     * @return Builder<SubscriptionTransaction>
     */
    private function charges(DateRange $range): Builder
    {
        return $this->ledger($range)->charges();
    }

    /**
     * Charges in the reporting currency — the base of every money figure.
     *
     * @return Builder<SubscriptionTransaction>
     */
    private function sales(DateRange $range): Builder
    {
        return $this->charges($range)->where('subscription_transactions.currency', $this->currency());
    }

    /** A minor-unit sum in the reporting currency as a major-unit float. */
    private function major(int|float|string|null $minor): float
    {
        return Currency::toMajor($minor ?? 0, $this->currency());
    }
}
