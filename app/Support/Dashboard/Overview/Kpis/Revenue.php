<?php

namespace App\Support\Dashboard\Overview\Kpis;

use App\Support\Dashboard\Blocks\Metric;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\RevenueMetrics;
use App\Support\Dashboard\Overview\OverviewWidget;

/** Revenue collected in the window against the window before it. */
class Revenue extends OverviewWidget
{
    protected ?string $permission = 'subscriptions.view';

    public function __construct(private readonly RevenueMetrics $revenue) {}

    public function build(DateRange $range): Metric
    {
        return Metric::make(__('dashboard.kpis.revenue'), $this->revenue->revenue($range), Format::CURRENCY)
            ->compareTo($this->revenue->revenue($range->previous()))
            ->description(__('dashboard.kpis.transactions', ['count' => number_format($this->revenue->transactions($range))]))
            ->icon('banknote');
    }
}
