<?php

namespace App\Support\Dashboard\Reports\Definitions;

use App\Enum\CancelledBy;
use App\Enum\SubscriptionStatus;
use App\Models\Plan;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\RevenueMetrics;
use App\Support\Dashboard\Metrics\SubscriptionMetrics;
use App\Support\Dashboard\Reports\ReportDefinition;
use App\Support\Dashboard\Reports\ReportFilter;
use Illuminate\Database\Eloquent\Builder;

/** One row per plan: how its subscriptions moved during the period. */
class SubscriptionSummaryReport extends ReportDefinition
{
    public function __construct(
        private readonly SubscriptionMetrics $subscriptions,
        private readonly RevenueMetrics $revenue,
    ) {}

    public function key(): string
    {
        return 'subscription_summary';
    }

    public function label(): string
    {
        return __('dashboard.reports.definitions.subscription_summary.label');
    }

    public function description(): string
    {
        return __('dashboard.reports.definitions.subscription_summary.description');
    }

    public function icon(): string
    {
        return 'credit-card';
    }

    public function permission(): ?string
    {
        return 'subscriptions.view';
    }

    public function filters(): array
    {
        return [
            ReportFilter::select('plan', __('dashboard.reports.filters.plan'), Plan::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all()),
        ];
    }

    public function columns(): array
    {
        return [
            'plan' => __('dashboard.reports.columns.plan'),
            'new' => __('dashboard.reports.columns.new'),
            'live' => __('dashboard.reports.columns.live'),
            'trialing' => SubscriptionStatus::Trialing->label(),
            'cancelled' => __('dashboard.reports.columns.cancelled'),
            'expired' => __('dashboard.reports.columns.expired'),
            'revenue' => __('dashboard.reports.columns.revenue_in', ['currency' => $this->revenue->currency()]),
        ];
    }

    public function rows(DateRange $range, array $filters): iterable
    {
        $between = [$range->start, $range->end];

        $plans = Plan::query()
            ->when($filters['plan'] ?? null, fn (Builder $query, string $plan) => $query->whereKey($plan))
            ->withCount([
                'subscriptions as new_count' => fn (Builder $query) => $query->whereNotNull('user_id')->whereBetween('starts_at', $between),
                'subscriptions as live_count' => fn (Builder $query) => $query->whereNotNull('user_id')->whereIn('status', SubscriptionMetrics::LIVE_STATUSES),
                'subscriptions as trialing_count' => fn (Builder $query) => $query->whereNotNull('user_id')->where('status', SubscriptionStatus::Trialing->value),
                'subscriptions as cancelled_count' => fn (Builder $query) => $query->whereNotNull('user_id')
                    ->whereNotNull('cancelled_by')
                    ->where('cancelled_by', '!=', CancelledBy::System->value)
                    ->whereBetween('updated_at', $between),
                'subscriptions as expired_count' => fn (Builder $query) => $query->whereNotNull('user_id')
                    ->where('status', SubscriptionStatus::Expired->value)
                    ->whereBetween('ends_at', $between),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $sales = $this->revenue->revenueByPlanId($range);

        foreach ($plans as $plan) {
            yield [
                'plan' => $plan->name,
                'new' => (int) $plan->getAttribute('new_count'),
                'live' => (int) $plan->getAttribute('live_count'),
                'trialing' => (int) $plan->getAttribute('trialing_count'),
                'cancelled' => (int) $plan->getAttribute('cancelled_count'),
                'expired' => (int) $plan->getAttribute('expired_count'),
                'revenue' => $sales[$plan->id] ?? 0.0,
            ];
        }
    }

    public function summary(DateRange $range, array $filters): array
    {
        return [
            __('dashboard.reports.summary.new_subscriptions') => Format::value($this->subscriptions->started($range)),
            __('dashboard.reports.summary.live_subscriptions') => Format::value($this->subscriptions->live()),
            __('dashboard.reports.summary.churn_rate') => Format::value($this->subscriptions->churnRate($range), Format::PERCENT),
            __('dashboard.reports.summary.trial_conversion') => Format::value($this->subscriptions->trialConversionRate($range), Format::PERCENT),
        ];
    }
}
