<?php

namespace App\Support\Dashboard\Overview\Kpis;

use App\Support\Dashboard\Blocks\Metric;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Metrics\AudienceMetrics;
use App\Support\Dashboard\Overview\OverviewWidget;

/** App users today, with growth since the window began. */
class TotalUsers extends OverviewWidget
{
    protected ?string $permission = 'users.view';

    public function __construct(private readonly AudienceMetrics $audience) {}

    public function build(DateRange $range): Metric
    {
        return Metric::make(__('dashboard.kpis.total_users'), $this->audience->totalUsers())
            ->compareTo($this->audience->totalUsers($range->start->subSecond()))
            ->description(__('dashboard.kpis.new_in_period', ['count' => number_format($this->audience->newUsers($range))]))
            ->icon('users')
            ->link(route('admin.users.index'), 'users.view');
    }
}
