<?php

namespace App\Support\Dashboard\Overview\Kpis;

use App\Support\Dashboard\Blocks\Metric;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Metrics\SupportMetrics;
use App\Support\Dashboard\Overview\OverviewWidget;

/**
 * The support backlog right now. No history of past backlog sizes is kept,
 * so this KPI carries no trend chip rather than an invented one.
 */
class OpenTickets extends OverviewWidget
{
    protected ?string $permission = 'tickets.view';

    public function __construct(private readonly SupportMetrics $support) {}

    public function build(DateRange $range): Metric
    {
        return Metric::make(__('dashboard.kpis.open_tickets'), $this->support->backlog())
            ->description(__('dashboard.kpis.unassigned', ['count' => number_format($this->support->unassigned())]))
            ->icon('life-buoy')
            ->invert()
            ->link(route('admin.tickets.index'), 'tickets.view');
    }
}
