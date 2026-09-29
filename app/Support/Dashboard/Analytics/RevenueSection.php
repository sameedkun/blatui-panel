<?php

namespace App\Support\Dashboard\Analytics;

use App\Support\Dashboard\Blocks\BarList;
use App\Support\Dashboard\Blocks\Chart;
use App\Support\Dashboard\Blocks\KeyFigures;
use App\Support\Dashboard\Blocks\Metric;
use App\Support\Dashboard\Blocks\Row;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\RevenueMetrics;
use App\Support\Dashboard\TimeSeries;

/** Understand money coming into the platform. */
class RevenueSection extends AnalyticsSection
{
    public function __construct(private readonly RevenueMetrics $revenue) {}

    public function key(): string
    {
        return 'revenue';
    }

    public function label(): string
    {
        return __('dashboard.sections.revenue.label');
    }

    public function description(): string
    {
        return __('dashboard.sections.revenue.description');
    }

    public function icon(): string
    {
        return 'banknote';
    }

    public function permission(): ?string
    {
        return 'subscriptions.view';
    }

    public function build(DateRange $range): array
    {
        $previous = $range->previous();
        $series = $this->revenue->revenueSeries($range);
        $refunds = $this->revenue->refunds($range);
        $outcomes = $this->revenue->transactionOutcomes($range);

        return [
            Row::columns(4,
                Metric::make(__('dashboard.metrics.revenue'), $this->revenue->revenue($range), Format::CURRENCY)
                    ->compareTo($this->revenue->revenue($previous))
                    ->icon('banknote'),
                Metric::make(__('dashboard.metrics.mrr'), $this->revenue->mrr(), Format::CURRENCY)
                    ->compareTo($this->revenue->mrr($range->start))
                    ->description(__('dashboard.metrics.mrr_hint'))
                    ->icon('repeat'),
                Metric::make(__('dashboard.metrics.transactions'), $this->revenue->transactions($range))
                    ->compareTo($this->revenue->transactions($previous))
                    ->icon('receipt'),
                Metric::make(__('dashboard.metrics.refunds'), $refunds['amount'], Format::CURRENCY)
                    ->compareTo($this->revenue->refunds($previous)['amount'])
                    ->description(__('dashboard.metrics.refund_count', ['count' => number_format($refunds['count'])]))
                    ->icon('undo-2')
                    ->invert(),
            ),

            Row::columns(1,
                Chart::make(__('dashboard.charts.revenue_over_time'), Chart::AREA)
                    ->description(__('dashboard.charts.revenue_over_time_hint', ['currency' => config('dashboard.currency')]))
                    ->icon('chart-area')
                    ->summary(array_sum($series), Format::CURRENCY)
                    ->labels(array_values($range->buckets()))
                    ->series(__('dashboard.series.revenue'), array_values($series))
                    ->series(__('dashboard.series.previous_period'), TimeSeries::alignPrevious($this->revenue->revenueSeries($previous), count($series)))
                    ->colors(['var(--chart-1)', 'var(--muted-foreground)'])
                    ->dashed(1)
                    ->height(320),
            ),

            Row::columns(2,
                BarList::make(__('dashboard.lists.revenue_by_plan'), Format::CURRENCY)
                    ->icon('package')
                    ->items($this->revenue->revenueByPlan($range))
                    ->link(route('admin.plans.index'), 'plans.view'),
                BarList::make(__('dashboard.lists.revenue_by_billing'), Format::CURRENCY)
                    ->icon('calendar-sync')
                    ->items($this->revenue->revenueByBilling($range)),
            ),

            Row::columns(2,
                BarList::make(__('dashboard.lists.transactions'))
                    ->description(__('dashboard.lists.transactions_hint'))
                    ->icon('arrow-left-right')
                    ->item(__('dashboard.labels.paid'), $outcomes['paid'], tone: 'success')
                    ->item(__('dashboard.labels.trials_started'), $outcomes['trials'], tone: 'info')
                    ->item(__('dashboard.labels.failed'), $outcomes['failed'], tone: 'danger')
                    ->item(__('dashboard.labels.refunded'), $outcomes['refunded'], tone: 'warning')
                    ->link(route('admin.subscriptions.index'), 'subscriptions.view'),
                BarList::make(__('dashboard.lists.revenue_by_provider'), Format::CURRENCY)
                    ->icon('wallet')
                    ->items($this->revenue->revenueByProvider($range)),
            ),

            Row::columns(1,
                KeyFigures::make(__('dashboard.figures.unit_economics'))
                    ->icon('calculator')
                    ->figure(__('dashboard.figures.arpu'), $this->revenue->arpu($range), Format::CURRENCY, __('dashboard.figures.arpu_hint'))
                    ->figure(__('dashboard.figures.average_order'), $this->revenue->averageOrderValue($range), Format::CURRENCY)
                    ->figure(__('dashboard.figures.paying_customers'), $this->revenue->payingCustomers($range))
                    ->figure(__('dashboard.figures.arr'), $this->revenue->arr(), Format::CURRENCY, __('dashboard.figures.arr_hint')),
            ),
        ];
    }
}
