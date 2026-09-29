<?php

namespace App\Support\Dashboard\Overview\Status;

use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Support\Dashboard\Blocks\StatusPanel;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\SupportMetrics;
use App\Support\Dashboard\Overview\OverviewWidget;

/** The support queue: backlog and speed up top, the backlog's urgency beneath. */
class SupportStatus extends OverviewWidget
{
    protected ?string $permission = 'tickets.view';

    public function __construct(private readonly SupportMetrics $support) {}

    public function build(DateRange $range): StatusPanel
    {
        $priorities = $this->support->backlogByPriority();
        $times = $this->support->responseTimes($range);

        return StatusPanel::make(__('dashboard.status.support'))
            ->description(__('dashboard.status.support_hint'))
            ->icon('life-buoy')
            ->figure(TicketStatus::Open->label(), $this->support->countByStatus(TicketStatus::Open))
            ->figure(TicketStatus::Pending->label(), $this->support->countByStatus(TicketStatus::Pending))
            ->figure(__('dashboard.status.unassigned'), $this->support->unassigned())
            ->figure(__('dashboard.status.first_response'), $times['first_response'], Format::DURATION)
            ->segmentsTitle(__('dashboard.status.backlog_by_priority'))
            ->segment(TicketPriority::Urgent->label(), $priorities[TicketPriority::Urgent->value], 'danger')
            ->segment(TicketPriority::High->label(), $priorities[TicketPriority::High->value], 'warning')
            ->segment(TicketPriority::Medium->label(), $priorities[TicketPriority::Medium->value], 'info')
            ->segment(TicketPriority::Low->label(), $priorities[TicketPriority::Low->value])
            ->link(route('admin.dashboard.analytics', ['tab' => 'support']), 'dashboard.analytics.view');
    }
}
