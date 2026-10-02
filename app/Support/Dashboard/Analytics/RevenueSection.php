<?php

namespace App\Support\Dashboard\Analytics;

use App\Support\Dashboard\Blocks\BarList;
use App\Support\Dashboard\Blocks\Chart;
use App\Support\Dashboard\Blocks\KeyFigures;
use App\Support\Dashboard\Blocks\Metric;
use App\Support\Dashboard\Blocks\Row;
use App\Support\Dashboard\Blocks\Table;
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
        $currency = $this->revenue->currency();
        $otherCurrencies = $this->revenue->otherCurrencies($range);
        $mrrOtherCurrencies = $this->revenue->mrrOtherCurrencies();

        return [
            Row::columns(4,
                Metric::make(__('dashboard.metrics.revenue'), $this->revenue->revenue($range), Format::CURRENCY)
                    ->compareTo($this->revenue->revenue($previous))
                    ->description($otherCurrencies > 0
                        ? __('dashboard.metrics.revenue_other_currencies', ['currency' => $currency, 'count' => $otherCurrencies])
                        : __('dashboard.metrics.revenue_hint', ['currency' => $currency]))
                    ->icon('banknote'),
                Metric::make(__('dashboard.metrics.mrr'), $this->revenue->mrr(), Format::CURRENCY)
                    ->compareTo($this->revenue->mrr($range->start))
                    ->description($mrrOtherCurrencies > 0
                        ? __('dashboard.metrics.mrr_other_currencies', ['currency' => $currency, 'count' => $mrrOtherCurrencies])
                        : __('dashboard.metrics.mrr_hint'))
                    ->icon('repeat'),
                Metric::make(__('dashboard.metrics.transactions'), $this->revenue->transactions($range))
                    ->compareTo($this->revenue->transactions($previous))
                    ->description(__('dashboard.metrics.all_currencies'))
                    ->icon('receipt'),
                Metric::make(__('dashboard.metrics.refunds'), $refunds['amount'], Format::CURRENCY)
                    ->compareTo($this->revenue->refunds($previous)['amount'])
                    ->description(__('dashboard.metrics.refund_count', ['count' => number_format($refunds['count']), 'currency' => $currency]))
                    ->icon('undo-2')
                    ->invert(),
            ),

            Row::columns(1,
                Chart::make(__('dashboard.charts.revenue_over_time'), Chart::AREA)
                    ->description(__('dashboard.charts.revenue_over_time_hint', ['currency' => $currency]))
                    ->icon('chart-area')
                    ->summary(array_sum($series), Format::CURRENCY)
                    ->labels(array_values($range->buckets()))
                    ->series(__('dashboard.series.revenue'), array_values($series))
                    ->series(__('dashboard.series.previous_period'), TimeSeries::alignPrevious($this->revenue->revenueSeries($previous), count($series)))
                    ->colors(['var(--chart-1)', 'var(--muted-foreground)'])
                    ->dashed(1)
                    ->height(320),
            ),

            Row::columns(1, $this->salesByCurrency($range)),

            Row::columns(2,
                BarList::make(__('dashboard.lists.revenue_by_plan'), Format::CURRENCY)
                    ->description(__('dashboard.lists.revenue_in', ['currency' => $currency]))
                    ->icon('package')
                    ->items($this->revenue->revenueByPlan($range))
                    ->link(route('admin.plans.index'), 'plans.view'),
                BarList::make(__('dashboard.lists.revenue_by_billing'), Format::CURRENCY)
                    ->description(__('dashboard.lists.revenue_in', ['currency' => $currency]))
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
                    ->description(__('dashboard.lists.revenue_in', ['currency' => $currency]))
                    ->icon('wallet')
                    ->items($this->revenue->revenueByProvider($range)),
            ),

            Row::columns(1,
                KeyFigures::make(__('dashboard.figures.unit_economics'))
                    ->icon('calculator')
                    ->figure(__('dashboard.figures.arpu'), $this->revenue->arpu($range), Format::CURRENCY, __('dashboard.figures.arpu_hint', ['currency' => $currency]))
                    ->figure(__('dashboard.figures.average_order'), $this->revenue->averageOrderValue($range), Format::CURRENCY, __('dashboard.figures.average_order_hint', ['currency' => $currency]))
                    ->figure(__('dashboard.figures.paying_customers'), $this->revenue->payingCustomers($range, $currency), Format::NUMBER, __('dashboard.figures.paying_customers_hint', ['currency' => $currency]))
                    ->figure(__('dashboard.figures.arr'), $this->revenue->arr(), Format::CURRENCY, __('dashboard.figures.arr_hint', ['currency' => $currency])),
            ),
        ];
    }

    /**
     * Exact sales in every currency customers paid in — no conversion, so
     * each amount is formatted in its own currency and nothing is summed
     * across rows.
     */
    private function salesByCurrency(DateRange $range): Table
    {
        return Table::make(__('dashboard.tables.sales_by_currency'))
            ->description(__('dashboard.tables.sales_by_currency_hint'))
            ->icon('coins')
            ->column('currency', __('dashboard.columns.currency'))
            ->column('transactions', __('dashboard.columns.transactions'), Format::NUMBER)
            ->column('gross', __('dashboard.columns.gross_sales'), Format::TEXT, 'right')
            ->column('refunds', __('dashboard.columns.refunds'), Format::TEXT, 'right')
            ->column('net', __('dashboard.columns.net_sales'), Format::TEXT, 'right')
            ->rows(collect($this->revenue->salesByCurrency($range))->map(fn (array $sales, string $currency): array => [
                'currency' => $currency,
                'transactions' => $sales['transactions'],
                'gross' => $sales['gross']->format(),
                'refunds' => $sales['refunds']->format(),
                'net' => $sales['net']->format(),
            ])->values());
    }
}
