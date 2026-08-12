<?php

namespace App\Support\Dashboard;

use App\Enum\TicketMessageAuthorType;
use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Models\Ticket;
use App\Services\Ticket\AssignmentService;
use App\Support\Dashboard\Concerns\BuildsTimeSeries;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Support-desk analytics — queue health, throughput and agent load.
 *
 * Note these figures are deliberately unscoped by {@see Ticket::scopeVisibleTo()}: the
 * dashboard's support widgets are gated on `tickets.view` as a whole and are meant to
 * describe the desk, not one agent's slice of it. Per-agent visibility rules still apply
 * everywhere a ticket is actually opened.
 */
class SupportMetrics
{
    use BuildsTimeSeries;

    public function __construct(private readonly AssignmentService $assignments) {}

    /** Statuses that still need somebody to do something. */
    public const OPEN_STATUSES = [
        TicketStatus::Open->value,
        TicketStatus::Pending->value,
    ];

    public function openTickets(): int
    {
        return Ticket::query()->whereIn('status', self::OPEN_STATUSES)->count();
    }

    public function unassignedTickets(): int
    {
        return Ticket::query()
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereNull('assigned_to')
            ->count();
    }

    /** Tickets raised inside the window, with its change against the previous window. */
    public function newTickets(DateRange $range): array
    {
        $count = fn ($from, $to): int => Ticket::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $current = $count($range->start(), $range->end());
        $previous = $count($range->previousStart(), $range->previousEnd());

        return [
            'value' => $current,
            'previous' => $previous,
            'change' => $this->percentChange($current, $previous),
        ];
    }

    /**
     * Tickets opened vs closed over the window — is the desk keeping up?
     *
     * @return array{labels: array<int, string>, opened: array<int, int>, closed: array<int, int>}
     */
    public function volumeSeries(DateRange $range): array
    {
        $opened = $this->countByPeriod(Ticket::query(), $range);
        $closed = $this->countByPeriod(Ticket::query()->whereNotNull('closed_at'), $range, 'closed_at');

        return [
            'labels' => $this->bucketLabels($opened, $range),
            'opened' => array_values($opened),
            'closed' => array_values($closed),
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function statusBreakdown(): array
    {
        $counts = Ticket::query()
            ->groupBy('status')
            ->select(['status', DB::raw('COUNT(*) as aggregate')])
            ->pluck('aggregate', 'status')
            ->all();

        $labels = [];
        $values = [];

        foreach (TicketStatus::cases() as $status) {
            $labels[] = $status->label();
            $values[] = (int) ($counts[$status->value] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Priority mix across tickets that are still open.
     *
     * Closed tickets are excluded on purpose — this widget answers "what is waiting on us
     * right now", and years of resolved Low tickets would bury a live Urgent one.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function priorityBreakdown(): array
    {
        $counts = Ticket::query()
            ->whereIn('status', self::OPEN_STATUSES)
            ->groupBy('priority')
            ->select(['priority', DB::raw('COUNT(*) as aggregate')])
            ->pluck('aggregate', 'priority')
            ->all();

        $labels = [];
        $values = [];

        foreach (TicketPriority::cases() as $priority) {
            $labels[] = $priority->label();
            $values[] = (int) ($counts[$priority->value] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Median hours between a ticket being raised and the first staff reply.
     *
     * `tickets.last_staff_response_at` holds the *latest* reply, not the first, so it
     * can't answer this — the first staff message per ticket has to come from
     * `ticket_messages`. Median rather than mean: one ticket that sat over a holiday
     * weekend would drag an average far away from the typical experience.
     */
    public function medianFirstResponseHours(DateRange $range): ?float
    {
        $durations = DB::table('tickets')
            ->join('ticket_messages', 'ticket_messages.ticket_id', '=', 'tickets.id')
            ->where('ticket_messages.author_type', TicketMessageAuthorType::Staff->value)
            ->whereBetween('tickets.created_at', [$range->start(), $range->end()])
            ->groupBy('tickets.id', 'tickets.created_at')
            ->select([
                DB::raw($this->minutesBetweenExpression(
                    'tickets.created_at',
                    'MIN(ticket_messages.created_at)',
                ).' as minutes'),
            ])
            ->pluck('minutes')
            ->filter(fn ($m): bool => $m !== null && $m >= 0)
            ->sort()
            ->values();

        if ($durations->isEmpty()) {
            return null;
        }

        $count = $durations->count();
        $middle = (int) floor($count / 2);

        $median = $count % 2 === 0
            ? ($durations[$middle - 1] + $durations[$middle]) / 2
            : $durations[$middle];

        return round($median / 60, 1);
    }

    /**
     * Open-ticket load per eligible agent, busiest first.
     *
     * Built from the eligible pool rather than from ticket rows so an agent with nothing
     * assigned still shows up at zero — an idle agent is exactly what somebody looking at
     * a load-balancing widget needs to see.
     *
     * @return array<int, array{name: string, total: int, share: float}>
     */
    public function agentWorkload(int $limit = 6): array
    {
        $agents = $this->assignments->eligibleAgents();

        if ($agents->isEmpty()) {
            return [];
        }

        $counts = Ticket::query()
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereIn('assigned_to', $agents->pluck('id'))
            ->groupBy('assigned_to')
            ->select(['assigned_to', DB::raw('COUNT(*) as aggregate')])
            ->pluck('aggregate', 'assigned_to')
            ->all();

        $busiest = max(1, $counts ? max($counts) : 1);

        return $agents
            ->map(fn ($agent): array => [
                'name' => $agent->name,
                'total' => (int) ($counts[$agent->id] ?? 0),
                'share' => round(((int) ($counts[$agent->id] ?? 0)) / $busiest * 100, 1),
            ])
            ->sortByDesc('total')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * The open tickets that have been waiting longest.
     *
     * @return Collection<int, Ticket>
     */
    public function oldestOpen(int $limit = 6)
    {
        return Ticket::query()
            ->with(['user', 'agent', 'category'])
            ->whereIn('status', self::OPEN_STATUSES)
            ->oldest('created_at')
            ->limit($limit)
            ->get();
    }
}
