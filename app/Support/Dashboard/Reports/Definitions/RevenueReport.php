<?php

namespace App\Support\Dashboard\Reports\Definitions;

use App\Enum\PaymentProvider;
use App\Enum\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\RevenueMetrics;
use App\Support\Dashboard\Reports\ReportDefinition;
use App\Support\Dashboard\Reports\ReportFilter;
use Illuminate\Database\Eloquent\Builder;

/** Every subscription period sold in the window — the transaction ledger behind revenue. */
class RevenueReport extends ReportDefinition
{
    public function __construct(private readonly RevenueMetrics $revenue) {}

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
            'status' => __('dashboard.reports.columns.status'),
            'amount' => __('dashboard.reports.columns.amount'),
            'currency' => __('dashboard.reports.columns.currency'),
        ];
    }

    public function rows(DateRange $range, array $filters): iterable
    {
        foreach ($this->query($range, $filters)->with(['user' => fn ($query) => $query->withTrashed(), 'plan', 'planPrice'])->lazyById(500) as $subscription) {
            yield [
                'date' => $subscription->starts_at->format('Y-m-d H:i'),
                'customer' => $subscription->user?->name,
                'email' => $subscription->user?->email,
                'plan' => $subscription->plan?->name,
                'billing' => $subscription->planPrice
                    ? RevenueMetrics::billingLabel($subscription->planPrice->billing_interval, (int) $subscription->planPrice->billing_period)
                    : null,
                'provider' => $subscription->provider->label(),
                'status' => $subscription->status->label(),
                'amount' => $subscription->amount_paid === null ? null : round((float) $subscription->amount_paid, 2),
                'currency' => $subscription->currency,
            ];
        }
    }

    public function summary(DateRange $range, array $filters): array
    {
        $revenue = (float) $this->query($range, $filters)->sum('amount_paid');
        $transactions = $this->query($range, $filters)->where('amount_paid', '>', 0)->count();
        $refunds = $this->revenue->refunds($range);

        return [
            __('dashboard.reports.summary.revenue') => Format::currency($revenue),
            __('dashboard.reports.summary.transactions') => Format::value($transactions),
            __('dashboard.reports.summary.average_order') => Format::currency($transactions > 0 ? $revenue / $transactions : 0),
            __('dashboard.reports.summary.refunds') => Format::currency($refunds['amount']),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return Builder<Subscription>
     */
    private function query(DateRange $range, array $filters): Builder
    {
        return Subscription::query()
            ->whereBetween('starts_at', [$range->start, $range->end])
            ->when($filters['plan'] ?? null, fn (Builder $query, string $plan) => $query->where('plan_id', $plan))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['provider'] ?? null, fn (Builder $query, string $provider) => $query->where('provider', $provider));
    }
}
