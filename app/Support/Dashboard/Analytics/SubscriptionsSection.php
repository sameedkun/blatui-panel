<?php

namespace App\Support\Dashboard\Analytics;

use App\Enum\SubscriptionStatus;
use App\Support\Dashboard\Blocks\BarList;
use App\Support\Dashboard\Blocks\Chart;
use App\Support\Dashboard\Blocks\Funnel;
use App\Support\Dashboard\Blocks\KeyFigures;
use App\Support\Dashboard\Blocks\Metric;
use App\Support\Dashboard\Blocks\Row;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\RevenueMetrics;
use App\Support\Dashboard\Metrics\SubscriptionMetrics;

/** Understand the subscription lifecycle. */
class SubscriptionsSection extends AnalyticsSection
{
    public function __construct(
        private readonly SubscriptionMetrics $subscriptions,
        private readonly RevenueMetrics $revenue,
    ) {}

    public function key(): string
    {
        return 'subscriptions';
    }

    public function label(): string
    {
        return __('dashboard.sections.subscriptions.label');
    }

    public function description(): string
    {
        return __('dashboard.sections.subscriptions.description');
    }

    public function icon(): string
    {
        return 'credit-card';
    }

    public function permission(): ?string
    {
        return 'subscriptions.view';
    }

    public function build(DateRange $range): array
    {
        $previous = $range->previous();
        $breakdown = $this->subscriptions->statusBreakdown();
        $movement = $this->revenue->mrrMovement($range);
        $averageDuration = $this->subscriptions->averageDurationDays($range);

        $tones = [
            SubscriptionStatus::Active->value => 'success',
            SubscriptionStatus::Trialing->value => 'info',
            SubscriptionStatus::Grace->value => 'warning',
            SubscriptionStatus::Cancelled->value => 'danger',
            SubscriptionStatus::Failed->value => 'danger',
        ];

        $statusList = BarList::make(__('dashboard.lists.status_breakdown'))
            ->icon('chart-bar')
            ->link(route('admin.subscriptions.index'), 'subscriptions.view');

        foreach ($breakdown as $status => $count) {
            $statusList->item(SubscriptionStatus::from($status)->label(), $count, tone: $tones[$status] ?? null);
        }

        return [
            Row::columns(4,
                Metric::make(SubscriptionStatus::Active->label(), $breakdown[SubscriptionStatus::Active->value])
                    ->icon('circle-check'),
                Metric::make(SubscriptionStatus::Trialing->label(), $breakdown[SubscriptionStatus::Trialing->value])
                    ->icon('hourglass'),
                Metric::make(__('dashboard.metrics.new_subscriptions'), $this->subscriptions->started($range))
                    ->compareTo($this->subscriptions->started($previous))
                    ->icon('circle-plus'),
                Metric::make(__('dashboard.metrics.cancellations'), $this->subscriptions->cancelled($range))
                    ->compareTo($this->subscriptions->cancelled($previous))
                    ->icon('circle-slash')
                    ->invert(),
            ),

            Row::columns(1,
                Chart::make(__('dashboard.charts.subscription_growth'), Chart::BAR)
                    ->description(__('dashboard.charts.subscription_growth_hint'))
                    ->icon('chart-column')
                    ->labels(array_values($range->buckets()))
                    ->series(__('dashboard.series.started'), array_values($this->subscriptions->startedSeries($range)))
                    ->series(__('dashboard.series.cancelled'), array_values($this->subscriptions->cancelledSeries($range)))
                    ->series(__('dashboard.series.expired'), array_values($this->subscriptions->expiredSeries($range)))
                    ->colors(['var(--chart-2)', 'var(--chart-5)', 'var(--chart-3)']),
            ),

            Row::columns(2,
                $statusList,
                BarList::make(__('dashboard.lists.plans'))
                    ->description(__('dashboard.lists.plans_hint'))
                    ->icon('package')
                    ->items($this->subscriptions->planDistribution())
                    ->link(route('admin.plans.index'), 'plans.view'),
            ),

            Row::columns(1,
                Funnel::make(__('dashboard.funnels.lifecycle'))
                    ->description(__('dashboard.funnels.lifecycle_hint'))
                    ->icon('workflow')
                    ->withoutConversion()
                    ->step(__('dashboard.funnels.new'), $this->subscriptions->started($range), __('dashboard.funnels.new_hint'), 'info')
                    ->step(__('dashboard.funnels.active'), $this->subscriptions->live(), __('dashboard.funnels.active_hint'), 'success')
                    ->step(__('dashboard.funnels.renewed'), $this->subscriptions->renewals($range), __('dashboard.funnels.renewed_hint'), 'success')
                    ->step(__('dashboard.funnels.expiring'), $this->subscriptions->expiringSoon(), __('dashboard.funnels.expiring_hint'), 'warning')
                    ->step(__('dashboard.funnels.cancelled'), $this->subscriptions->cancelled($range), __('dashboard.funnels.cancelled_hint'), 'danger'),
            ),

            Row::columns(1,
                KeyFigures::make(__('dashboard.figures.retention_health'))
                    ->icon('heart-pulse')
                    ->figure(__('dashboard.figures.churn_rate'), $this->subscriptions->churnRate($range), Format::PERCENT, __('dashboard.figures.churn_rate_hint'))
                    ->figure(__('dashboard.figures.renewal_rate'), $this->subscriptions->renewalRate($range), Format::PERCENT)
                    ->figure(__('dashboard.figures.trial_conversion'), $this->subscriptions->trialConversionRate($range), Format::PERCENT)
                    ->figure(__('dashboard.figures.average_duration'), $averageDuration === null ? null : __('dashboard.figures.days', ['count' => number_format($averageDuration, 1)]), Format::TEXT)
                    ->figure(__('dashboard.figures.new_mrr'), $movement['new'], Format::CURRENCY)
                    ->figure(__('dashboard.figures.churned_mrr'), $movement['churned'], Format::CURRENCY)
                    ->figure(__('dashboard.figures.net_mrr'), $movement['net'], Format::CURRENCY, __('dashboard.figures.net_mrr_hint', ['currency' => $this->revenue->currency()])),
            ),
        ];
    }
}
