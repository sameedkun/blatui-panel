<?php

namespace App\Support\Dashboard\Reports\Definitions;

use App\Enum\PaymentProvider;
use App\Enum\SubscriptionStatus;
use App\Models\Plan;
use App\Models\SubscriptionTransaction;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\RevenueMetrics;
use App\Support\Dashboard\Reports\ReportDefinition;
use App\Support\Dashboard\Reports\ReportFilter;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every money movement in the window — the transaction ledger behind
 * revenue. One row per charge or refund, each in the currency the customer
 * paid (refunds negative); the summary totals each currency separately.
 */
class RevenueReport extends ReportDefinition
{
    public function key(): string
    {
        return 'revenue';
    }

    public function label(): string
    {
        return __('dashboard.reports.definitions.revenue.label');
    }

    public function description(): string
    {
        return __('dashboard.reports.definitions.revenue.description');
    }

    public function icon(): string
    {
        return 'banknote';
    }

    public function permission(): ?string
    {
        return 'subscriptions.view';
    }

    public function filters(): array
    {
        return [
            ReportFilter::select('plan', __('dashboard.reports.filters.plan'), Plan::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all()),
            ReportFilter::select('status', __('dashboard.reports.filters.status'), collect(SubscriptionStatus::cases())->mapWithKeys(fn (SubscriptionStatus $status): array => [$status->value => $status->label()])->all()),
            ReportFilter::select('provider', __('dashboard.reports.filters.provider'), collect(PaymentProvider::cases())->mapWithKeys(fn (PaymentProvider $provider): array => [$provider->value => $provider->label()])->all()),
        ];
    }

    public function columns(): array
    {
        return [
            'date' => __('dashboard.reports.columns.date'),
            'customer' => __('dashboard.reports.columns.customer'),
            'email' => __('dashboard.reports.columns.email'),
            'plan' => __('dashboard.reports.columns.plan'),
            'billing' => __('dashboard.reports.columns.billing'),
            'provider' => __('dashboard.reports.columns.provider'),
            'type' => __('dashboard.reports.columns.transaction_type'),
            'amount' => __('dashboard.reports.columns.amount'),
            'currency' => __('dashboard.reports.columns.currency'),
            'transaction_id' => __('dashboard.reports.columns.transaction_id'),
        ];
    }

    public function rows(DateRange $range, array $filters): iterable
    {
        $transactions = $this->query($range, $filters)->with([
            'subscription.user' => fn ($query) => $query->withTrashed(),
            'subscription.plan',
            'subscription.planPrice',
        ]);

        foreach ($transactions->lazyById(500) as $transaction) {
            $subscription = $transaction->subscription;
            $price = $subscription?->planPrice;

            yield [
                'date' => $transaction->purchased_at?->format('Y-m-d H:i'),
                'customer' => $subscription?->user?->name,
                'email' => $subscription?->user?->email,
                'plan' => $subscription?->plan?->name,
                'billing' => $price ? RevenueMetrics::billingLabel($price->billing_interval, (int) $price->billing_period) : null,
                'provider' => $transaction->provider->label(),
                'type' => $transaction->type->label(),
                'amount' => $transaction->signedMoney()?->toDecimal(),
                'currency' => $transaction->currency,
                'transaction_id' => $transaction->provider_transaction_id,
            ];
        }
    }

    public function summary(DateRange $range, array $filters): array
    {
        $charges = $this->query($range, $filters)->charges()->where('subscription_transactions.amount_minor', '>', 0)->count();
        $summary = [__('dashboard.reports.summary.transactions') => Format::value($charges)];

        foreach ($this->currencies($range, $filters) as $currency) {
            $totals = $this->totals($range, $filters, $currency);
            $summary[__('dashboard.reports.summary.gross_sales_in', ['currency' => $currency])] = $totals['gross']->format();

            if (! $totals['refunds']->isZero()) {
                $summary[__('dashboard.reports.summary.refunds_in', ['currency' => $currency])] = $totals['refunds']->format();
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, string>  $filters
     * @return list<string>
     */
    private function currencies(DateRange $range, array $filters): array
    {
        return $this->query($range, $filters)->priced()->distinct()->orderBy('currency')->pluck('subscription_transactions.currency')->all();
    }

    /**
     * Gross charges and net refunds in one currency, for the filtered rows.
     *
     * @param  array<string, string>  $filters
     * @return array{gross: Money, refunds: Money}
     */
    private function totals(DateRange $range, array $filters, string $currency): array
    {
        $rows = $this->query($range, $filters)->priced()->where('subscription_transactions.currency', $currency);
        $net = SubscriptionTransaction::netByCurrency(clone $rows)[$currency];
        $gross = (int) (clone $rows)->charges()->sum('subscription_transactions.amount_minor');

        return [
            'gross' => Money::ofMinor($gross, $currency),
            'refunds' => Money::ofMinor($gross - $net->minor, $currency),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return Builder<SubscriptionTransaction>
     */
    private function query(DateRange $range, array $filters): Builder
    {
        return SubscriptionTransaction::query()
            ->whereBetween('subscription_transactions.purchased_at', [$range->start, $range->end])
            ->when($filters['provider'] ?? null, fn (Builder $query, string $provider) => $query->where('subscription_transactions.provider', $provider))
            ->when(($filters['plan'] ?? null) || ($filters['status'] ?? null), fn (Builder $query) => $query->whereHas('subscription', fn (Builder $subscription) => $subscription
                ->when($filters['plan'] ?? null, fn (Builder $q, string $plan) => $q->where('plan_id', $plan))
                ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))));
    }
}
