<?php

namespace App\Support\Dashboard\Overview\Trends;

use App\Support\Dashboard\Blocks\Chart;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Metrics\AudienceMetrics;
use App\Support\Dashboard\Overview\OverviewWidget;
use App\Support\Dashboard\TimeSeries;

/** New app users per bucket, overlaid on the previous period's shape. */
class UserGrowth extends OverviewWidget
{
    protected ?string $permission = 'users.view';

    public function __construct(private readonly AudienceMetrics $audience) {}

    public function build(DateRange $range): Chart
    {
        $current = $this->audience->registrationSeries($range);

        return Chart::make(__('dashboard.trends.user_growth'), Chart::AREA)
            ->description(__('dashboard.trends.user_growth_hint'))
            ->icon('user-plus')
            ->summary(array_sum($current))
            ->labels(array_values($range->buckets()))
            ->series(__('dashboard.series.new_users'), array_values($current))
            ->series(__('dashboard.series.previous_period'), TimeSeries::alignPrevious($this->audience->registrationSeries($range->previous()), count($current)))
            ->colors(['var(--chart-2)', 'var(--muted-foreground)'])
            ->dashed(1)
            ->link(route('admin.dashboard.analytics', ['tab' => 'audience']), 'dashboard.analytics.view');
    }
}
