<?php

namespace App\Support\Dashboard\Reports\Definitions;

use App\Enum\TicketMessageAuthorType;
use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\SupportMetrics;
use App\Support\Dashboard\Reports\ReportDefinition;
use App\Support\Dashboard\Reports\ReportFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

/** Every ticket opened in the period, with its response and resolution times. */
class TicketsReport extends ReportDefinition
{
    public function __construct(private readonly SupportMetrics $support) {}

    public function key(): string
    {
        return 'tickets';
    }

    public function label(): string
    {
        return __('dashboard.reports.definitions.tickets.label');
    }

    public function description(): string
    {
        return __('dashboard.reports.definitions.tickets.description');
    }

    public function icon(): string
    {
        return 'ticket';
    }

    public function permission(): ?string
    {
        return 'tickets.view';
    }

    public function filters(): array
    {
        return [
            ReportFilter::select('status', __('dashboard.reports.filters.status'), collect(TicketStatus::cases())->mapWithKeys(fn (TicketStatus $status): array => [$status->value => $status->label()])->all()),
            ReportFilter::select('priority', __('dashboard.reports.filters.priority'), collect(TicketPriority::cases())->mapWithKeys(fn (TicketPriority $priority): array => [$priority->value => $priority->label()])->all()),
            ReportFilter::select('category', __('dashboard.reports.filters.category'), TicketCategory::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all()),
        ];
    }

    public function columns(): array
    {
        return [
            'id' => __('dashboard.reports.columns.ticket'),
            'subject' => __('dashboard.reports.columns.subject'),
            'requester' => __('dashboard.reports.columns.requester'),
            'category' => __('dashboard.reports.columns.category'),
            'priority' => __('dashboard.reports.columns.priority'),
            'status' => __('dashboard.reports.columns.status'),
            'agent' => __('dashboard.reports.columns.agent'),
            'created_at' => __('dashboard.reports.columns.created_at'),
            'first_response' => __('dashboard.reports.columns.first_response'),
            'resolution' => __('dashboard.reports.columns.resolution'),
        ];
    }

    public function rows(DateRange $range, array $filters): iterable
    {
        $tickets = $this->query($range, $filters)
            ->addSelect(['first_response_at' => TicketMessage::query()
                ->selectRaw('MIN(ticket_messages.created_at)')
                ->whereColumn('ticket_messages.ticket_id', 'tickets.id')
                ->where('ticket_messages.author_type', TicketMessageAuthorType::Staff->value)])
            ->with(['user' => fn ($query) => $query->withTrashed(), 'category', 'agent' => fn ($query) => $query->withTrashed()]);

        foreach ($tickets->lazyById(500, 'tickets.id', 'id') as $ticket) {
            $resolvedAt = $ticket->closed_at ?? ($ticket->status->isTerminal() ? $ticket->updated_at : null);

            yield [
                'id' => '#'.$ticket->id,
                'subject' => $ticket->subject,
                'requester' => $ticket->user?->email,
                'category' => $ticket->category?->name,
                'priority' => $ticket->priority->label(),
                'status' => $ticket->status->label(),
                'agent' => $ticket->agent?->name,
                'created_at' => $ticket->created_at?->format('Y-m-d H:i'),
                'first_response' => $ticket->getAttribute('first_response_at') === null
                    ? null
                    : Format::duration($ticket->created_at->diffInSeconds(Date::parse($ticket->getAttribute('first_response_at'))) / 60),
                'resolution' => $resolvedAt === null ? null : Format::duration($ticket->created_at->diffInSeconds($resolvedAt) / 60),
            ];
        }
    }

    public function summary(DateRange $range, array $filters): array
    {
        $times = $this->support->responseTimes($range);

        return [
            __('dashboard.reports.summary.tickets') => Format::value($this->query($range, $filters)->count()),
            __('dashboard.reports.summary.resolved') => Format::value($this->support->resolved($range)),
            __('dashboard.reports.summary.first_response') => Format::value($times['first_response'], Format::DURATION),
            __('dashboard.reports.summary.resolution') => Format::value($times['resolution'], Format::DURATION),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return Builder<Ticket>
     */
    private function query(DateRange $range, array $filters): Builder
    {
        return Ticket::query()
            ->select('tickets.*')
            ->whereBetween('tickets.created_at', [$range->start, $range->end])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('tickets.status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('tickets.priority', $priority))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('tickets.category_id', $category));
    }
}
