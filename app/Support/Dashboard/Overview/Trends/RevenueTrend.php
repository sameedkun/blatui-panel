<?php

namespace App\Support\Dashboard\Overview\Trends;

use App\Support\Dashboard\Blocks\Chart;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\RevenueMetrics;
use App\Support\Dashboard\Overview\OverviewWidget;
use App\Support\Dashboard\TimeSeries;

/** Revenue per bucket, overlaid on the previous period's shape. */
class RevenueTrend extends OverviewWidget
{
    protected ?string $permission = 'subscriptions.view';

    public function __construct(private readonly RevenueMetrics $revenue) {}

    public function build(DateRange $range): Chart
    {
        $current = $this->revenue->revenueSeries($range);

        return Chart::make(__('dashboard.trends.revenue'), Chart::AREA)
            ->description(__('dashboard.trends.revenue_hint', ['currency' => $this->revenue->currency()]))
            ->icon('banknote')
            ->summary(array_sum($current), Format::CURRENCY)
            ->labels(array_values($range->buckets()))
            ->series(__('dashboard.series.revenue'), array_values($current))
            ->series(__('dashboard.series.previous_period'), TimeSeries::alignPrevious($this->revenue->revenueSeries($range->previous()), count($current)))
            ->colors(['var(--chart-1)', 'var(--muted-foreground)'])
            ->dashed(1)
            ->link(route('admin.dashboard.analytics', ['tab' => 'revenue']), 'dashboard.analytics.view');
    }
}
