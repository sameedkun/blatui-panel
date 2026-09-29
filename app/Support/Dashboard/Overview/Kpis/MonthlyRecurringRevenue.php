<?php

namespace App\Support\Dashboard\Overview\Kpis;

use App\Support\Dashboard\Blocks\Metric;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\RevenueMetrics;
use App\Support\Dashboard\Overview\OverviewWidget;

/** MRR now, against MRR at the start of the window. */
class MonthlyRecurringRevenue extends OverviewWidget
{
    protected ?string $permission = 'subscriptions.view';

    public function __construct(private readonly RevenueMetrics $revenue) {}

    public function build(DateRange $range): Metric
    {
        return Metric::make(__('dashboard.kpis.mrr'), $this->revenue->mrr(), Format::CURRENCY)
            ->compareTo($this->revenue->mrr($range->start))
            ->description(__('dashboard.kpis.arr', ['value' => Format::currency($this->revenue->arr())]))
            ->icon('repeat');
    }
}
