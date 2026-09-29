<?php

namespace App\Support\Dashboard\Analytics;

use App\Enum\TicketStatus;
use App\Support\Dashboard\Blocks\BarList;
use App\Support\Dashboard\Blocks\Chart;
use App\Support\Dashboard\Blocks\KeyFigures;
use App\Support\Dashboard\Blocks\Metric;
use App\Support\Dashboard\Blocks\Row;
use App\Support\Dashboard\Blocks\Table;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\SupportMetrics;

/** Understand support workload and performance. */
class SupportSection extends AnalyticsSection
{
    public function __construct(private readonly SupportMetrics $support) {}

    public function key(): string
    {
        return 'support';
    }

    public function label(): string
    {
        return __('dashboard.sections.support.label');
    }

    public function description(): string
    {
        return __('dashboard.sections.support.description');
    }

    public function icon(): string
    {
        return 'life-buoy';
    }

    public function permission(): ?string
    {
        return 'tickets.view';
    }

    public function build(DateRange $range): array
    {
        $previous = $range->previous();
        $times = $this->support->responseTimes($range);
        $volume = $this->support->volumeSeries($range);

        $tones = [
            TicketStatus::Open->value => 'info',
            TicketStatus::Pending->value => 'warning',
            TicketStatus::Resolved->value => 'success',
        ];

        $statusList = BarList::make(__('dashboard.lists.tickets_by_status'))
            ->icon('chart-bar')
            ->link(route('admin.tickets.index'), 'tickets.view');

        foreach ($this->support->statusBreakdown() as $status => $count) {
            $statusList->item(TicketStatus::from($status)->label(), $count, tone: $tones[$status] ?? null);
        }

        return [
            Row::columns(4,
                Metric::make(TicketStatus::Open->label(), $this->support->countByStatus(TicketStatus::Open))
                    ->description(__('dashboard.metrics.pending_count', ['count' => number_format($this->support->countByStatus(TicketStatus::Pending))]))
                    ->icon('inbox'),
                Metric::make(__('dashboard.metrics.new_tickets'), $this->support->created($range))
                    ->compareTo($this->support->created($previous))
                    ->icon('ticket-plus')
                    ->invert(),
                Metric::make(__('dashboard.metrics.resolved_tickets'), $this->support->resolved($range))
                    ->compareTo($this->support->resolved($previous))
                    ->icon('circle-check'),
                Metric::make(__('dashboard.metrics.first_response'), $times['first_response'], Format::DURATION)
                    ->compareTo($this->support->responseTimes($previous)['first_response'])
                    ->description(__('dashboard.metrics.first_response_hint'))
                    ->icon('timer')
                    ->invert(),
            ),

            Row::columns(1,
                Chart::make(__('dashboard.charts.ticket_volume'), Chart::BAR)
                    ->description(__('dashboard.charts.ticket_volume_hint'))
                    ->icon('chart-column')
                    ->labels(array_values($range->buckets()))
                    ->series(__('dashboard.series.opened'), array_values($volume['created']))
                    ->series(__('dashboard.series.resolved'), array_values($volume['resolved']))
                    ->colors(['var(--chart-1)', 'var(--chart-2)']),
            ),

            Row::columns(2,
                $statusList,
                BarList::make(__('dashboard.lists.tickets_by_category'))
                    ->description(__('dashboard.lists.tickets_by_category_hint'))
                    ->icon('tags')
                    ->items($this->support->categoryBreakdown($range))
                    ->link(route('admin.ticket-categories.index'), 'ticket_categories.view'),
            ),

            Row::columns(1,
                Table::make(__('dashboard.tables.agent_performance'))
                    ->description(__('dashboard.tables.agent_performance_hint'))
                    ->icon('user-check')
                    ->column('agent', __('dashboard.columns.agent'))
                    ->column('assigned', __('dashboard.columns.assigned'), Format::NUMBER)
                    ->column('resolved', __('dashboard.columns.resolved'), Format::NUMBER)
                    ->column('open', __('dashboard.columns.open'), Format::NUMBER)
                    ->column('first_response', __('dashboard.columns.first_response'), Format::DURATION)
                    ->column('resolution', __('dashboard.columns.resolution'), Format::DURATION)
                    ->rows($this->support->agentPerformance($range)),
            ),

            Row::columns(1,
                KeyFigures::make(__('dashboard.figures.queue_health'))
                    ->icon('gauge')
                    ->figure(__('dashboard.figures.average_resolution'), $times['resolution'], Format::DURATION)
                    ->figure(__('dashboard.figures.responded'), $times['responded'], Format::PERCENT, __('dashboard.figures.responded_hint'))
                    ->figure(__('dashboard.figures.unassigned'), $this->support->unassigned())
                    ->figure(__('dashboard.figures.urgent_backlog'), $this->support->urgentBacklog(), Format::NUMBER, __('dashboard.figures.urgent_backlog_hint'))
                    ->figure(__('dashboard.figures.oldest_waiting'), $this->support->oldestBacklogMinutes(), Format::DURATION),
            ),
        ];
    }
}
