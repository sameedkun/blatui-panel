<?php

namespace App\Support\Dashboard\Overview\Kpis;

use App\Support\Dashboard\Blocks\Metric;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\RevenueMetrics;
use App\Support\Dashboard\Overview\OverviewWidget;

/**
 * Gross sales in the reporting currency against the window before it. When
 * customers also paid in other currencies the description says so rather
 * than adding them in.
 */
class Revenue extends OverviewWidget
{
    protected ?string $permission = 'subscriptions.view';

    public function __construct(private readonly RevenueMetrics $revenue) {}

    public function build(DateRange $range): Metric
    {
        $currency = $this->revenue->currency();
        // The count sits under a reporting-currency amount, so it uses the same scope.
        $transactions = number_format($this->revenue->transactions($range, $currency));
        $otherCurrencies = $this->revenue->otherCurrencies($range);

        return Metric::make(__('dashboard.kpis.revenue'), $this->revenue->revenue($range), Format::CURRENCY)
            ->compareTo($this->revenue->revenue($range->previous()))
            ->description($otherCurrencies > 0
                ? __('dashboard.kpis.transactions_other_currencies', ['count' => $transactions, 'currency' => $currency, 'currencies' => $otherCurrencies])
                : __('dashboard.kpis.transactions', ['count' => $transactions, 'currency' => $currency]))
            ->icon('banknote')
            ->link(route('admin.dashboard.analytics', ['tab' => 'revenue']), 'dashboard.analytics.view');
    }
}
