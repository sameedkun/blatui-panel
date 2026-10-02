<?php

namespace App\Support\Dashboard\Overview\Kpis;

use App\Support\Dashboard\Blocks\Metric;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\RevenueMetrics;
use App\Support\Dashboard\Overview\OverviewWidget;

/**
 * MRR now, against MRR at the start of the window, with ARR beneath. Both are
 * in the reporting currency; subscriptions charged in another currency are
 * named as left out rather than silently counted as zero.
 */
class MonthlyRecurringRevenue extends OverviewWidget
{
    protected ?string $permission = 'subscriptions.view';

    public function __construct(private readonly RevenueMetrics $revenue) {}

    public function build(DateRange $range): Metric
    {
        $arr = Format::currency($this->revenue->arr());
        $otherCurrencies = $this->revenue->mrrOtherCurrencies();

        return Metric::make(__('dashboard.kpis.mrr'), $this->revenue->mrr(), Format::CURRENCY)
            ->compareTo($this->revenue->mrr($range->start))
            ->description($otherCurrencies > 0
                ? __('dashboard.kpis.arr_other_currencies', ['value' => $arr, 'currency' => $this->revenue->currency(), 'count' => $otherCurrencies])
                : __('dashboard.kpis.arr', ['value' => $arr]))
            ->icon('repeat');
    }
}
