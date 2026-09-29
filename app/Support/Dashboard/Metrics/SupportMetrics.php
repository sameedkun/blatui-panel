<?php

namespace App\Support\Dashboard\Metrics;

use App\Enum\TicketMessageAuthorType;
use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\TimeSeries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Support workload and performance.
 *
 * "First response" is the first staff-authored message on a ticket (system
 * notes — auto-assignment, re-routing — don't count as a response). "Resolved
 * at" is `closed_at` for closed tickets; a Resolved ticket has no dedicated
 * timestamp, so its last write (`updated_at`) stands in for it.
 *
 * These are aggregate figures across every ticket — not scoped by
 * Ticket::visibleTo() — so the section that shows them is gated on
 * `tickets.view` like the queue itself.
 */
class SupportMetrics
{
    /** Tickets still waiting on someone. */
    public const array BACKLOG_STATUSES = [TicketStatus::Open->value, TicketStatus::Pending->value];

    public function countByStatus(TicketStatus $status): int
    {
        return Ticket::query()->where('status', $status->value)->count();
    }

    public function backlog(): int
    {
        return Ticket::query()->whereIn('status', self::BACKLOG_STATUSES)->count();
    }

    public function unassigned(): int
    {
        return Ticket::query()->whereIn('status', self::BACKLOG_STATUSES)->whereNull('assigned_to')->count();
    }

    public function urgentBacklog(): int
    {
        return Ticket::query()
            ->whereIn('status', self::BACKLOG_STATUSES)
            ->whereIn('priority', [TicketPriority::High->value, TicketPriority::Urgent->value])
            ->count();
    }

    public function created(DateRange $range): int
    {
        return Ticket::query()->whereBetween('created_at', [$range->start, $range->end])->count();
    }

    public function resolved(DateRange $range): int
    {
        return $this->resolvedIn($range)->count();
    }

    /** Minutes the oldest ticket still in the backlog has been waiting, or null when there is none. */
    public function oldestBacklogMinutes(): ?float
    {
        $oldest = Ticket::query()->whereIn('status', self::BACKLOG_STATUSES)->min('created_at');

        return $oldest === null ? null : round(Date::parse($oldest)->diffInSeconds(Date::now()) / 60);
    }

    /**
     * Response and resolution averages for the tickets opened in the window.
     *
     * @return array{first_response: float|null, resolution: float|null, responded: float}
     */
    public function responseTimes(DateRange $range): array
    {
        $rows = $this->timings(Ticket::query()->whereBetween('tickets.created_at', [$range->start, $range->end]));
        $responses = $rows->pluck('first_response')->filter(fn (?float $minutes): bool => $minutes !== null);
        $resolutions = $rows->pluck('resolution')->filter(fn (?float $minutes): bool => $minutes !== null);

        return [
            'first_response' => $responses->isEmpty() ? null : round($responses->avg(), 1),
            'resolution' => $resolutions->isEmpty() ? null : round($resolutions->avg(), 1),
            'responded' => Format::share($responses->count(), $rows->count()),
        ];
    }

    /** @return array{created: array<string, int>, resolved: array<string, int>} tickets opened/resolved per bucket */
    public function volumeSeries(DateRange $range): array
    {
        $closed = TimeSeries::count(Ticket::query()->whereNotNull('closed_at'), $range, 'closed_at');
        $resolved = TimeSeries::count(
            Ticket::query()->whereNull('closed_at')->where('status', TicketStatus::Resolved->value),
            $range,
            'updated_at',
        );

        // Both series share the range's bucket keys, so they add up key for key.
        foreach ($closed as $bucket => $count) {
            $closed[$bucket] = $count + ($resolved[$bucket] ?? 0);
        }

        return [
            'created' => TimeSeries::count(Ticket::query(), $range),
            'resolved' => $closed,
        ];
    }

    /** @return array<string, int> status value => tickets, in lifecycle order */
    public function statusBreakdown(): array
    {
        $counts = Ticket::query()->groupBy('status')->toBase()->select(['status', DB::raw('COUNT(*) as aggregate')])->pluck('aggregate', 'status');

        $breakdown = [];

        foreach (TicketStatus::cases() as $status) {
            $breakdown[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $breakdown;
    }

    /** @return array<string, int> priority value => backlog tickets, most urgent first */
    public function backlogByPriority(): array
    {
        $counts = Ticket::query()
            ->whereIn('status', self::BACKLOG_STATUSES)
            ->groupBy('priority')
            ->toBase()
            ->select(['priority', DB::raw('COUNT(*) as aggregate')])
            ->pluck('aggregate', 'priority');

        $breakdown = [];

        foreach (array_reverse(TicketPriority::cases()) as $priority) {
            $breakdown[$priority->value] = (int) ($counts[$priority->value] ?? 0);
        }

        return $breakdown;
    }

    /** @return array<string, int> category name => tickets opened in the window, most first */
    public function categoryBreakdown(DateRange $range, int $limit = 8): array
    {
        return Ticket::query()
            ->leftJoin('categories', 'categories.id', '=', 'tickets.category_id')
            ->whereBetween('tickets.created_at', [$range->start, $range->end])
            ->groupBy('categories.name')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->toBase()
            ->select([DB::raw('COALESCE(categories.name, \'\') as name'), DB::raw('COUNT(*) as aggregate')])
            ->get()
            ->mapWithKeys(fn (object $row): array => [($row->name === '' ? __('dashboard.support.uncategorised') : $row->name) => (int) $row->aggregate])
            ->all();
    }

    /**
     * Per-agent workload and speed for tickets opened in the window.
     *
     * @return list<array{agent: string, assigned: int, resolved: int, open: int, first_response: float|null, resolution: float|null}>
     */
    public function agentPerformance(DateRange $range, int $limit = 10): array
    {
        $rows = $this->timings(
            Ticket::query()
                ->whereNotNull('tickets.assigned_to')
                ->whereBetween('tickets.created_at', [$range->start, $range->end]),
        );

        if ($rows->isEmpty()) {
            return [];
        }

        $names = User::withTrashed()->whereIn('id', $rows->pluck('assigned_to')->unique())->pluck('name', 'id');

        return $rows
            ->groupBy('assigned_to')
            ->map(function (Collection $tickets, int|string $agentId) use ($names): array {
                $responses = $tickets->pluck('first_response')->filter(fn (?float $minutes): bool => $minutes !== null);
                $resolutions = $tickets->pluck('resolution')->filter(fn (?float $minutes): bool => $minutes !== null);

                return [
                    'agent' => (string) ($names[$agentId] ?? '#'.$agentId),
                    'assigned' => $tickets->count(),
                    'resolved' => $resolutions->count(),
                    'open' => $tickets->filter(fn (array $ticket): bool => in_array($ticket['status'], self::BACKLOG_STATUSES, true))->count(),
                    'first_response' => $responses->isEmpty() ? null : round($responses->avg(), 1),
                    'resolution' => $resolutions->isEmpty() ? null : round($resolutions->avg(), 1),
                ];
            })
            ->sortByDesc('assigned')
            ->take($limit)
            ->values()
            ->all();
    }

    /** Tickets resolved or closed during the window. */
    public function resolvedIn(DateRange $range): Builder
    {
        return Ticket::query()->where(function (Builder $query) use ($range): void {
            $query->whereBetween('closed_at', [$range->start, $range->end])
                ->orWhere(fn (Builder $resolved) => $resolved
                    ->whereNull('closed_at')
                    ->where('status', TicketStatus::Resolved->value)
                    ->whereBetween('updated_at', [$range->start, $range->end]));
        });
    }

    /**
     * Each ticket's first-response and resolution time in minutes.
     *
     * One query: the first staff message comes from a correlated subquery,
     * and the (driver-specific) date arithmetic happens in PHP instead of SQL.
     *
     * @return Collection<int, array{assigned_to: int|null, status: string, first_response: float|null, resolution: float|null}>
     */
    public function timings(Builder $tickets): Collection
    {
        return $tickets
            ->select(['tickets.id', 'tickets.assigned_to', 'tickets.status', 'tickets.created_at', 'tickets.closed_at', 'tickets.updated_at'])
            ->addSelect(['first_response_at' => TicketMessage::query()
                ->selectRaw('MIN(ticket_messages.created_at)')
                ->whereColumn('ticket_messages.ticket_id', 'tickets.id')
                ->where('ticket_messages.author_type', TicketMessageAuthorType::Staff->value)])
            ->toBase()
            ->get()
            ->map(function (object $ticket): array {
                $created = Date::parse($ticket->created_at);
                $isResolved = in_array($ticket->status, [TicketStatus::Resolved->value, TicketStatus::Closed->value], true);
                $resolvedAt = $ticket->closed_at ?? ($isResolved ? $ticket->updated_at : null);

                return [
                    'assigned_to' => $ticket->assigned_to === null ? null : (int) $ticket->assigned_to,
                    'status' => (string) $ticket->status,
                    'first_response' => $ticket->first_response_at === null
                        ? null
                        : max(0.0, $created->diffInSeconds(Date::parse($ticket->first_response_at), false) / 60),
                    'resolution' => $resolvedAt === null
                        ? null
                        : max(0.0, $created->diffInSeconds(Date::parse($resolvedAt), false) / 60),
                ];
            });
    }
}
