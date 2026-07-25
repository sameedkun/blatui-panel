<?php

namespace App\Services;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityModule;
use App\Enum\TicketMessageAuthorType;
use App\Enum\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Notifications\Support\TicketAutoClosedNotification;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The calendar-driven counterpart to {@see TicketService} — mirrors
 * {@see SubscriptionLifecycleService}'s split from {@see SubscriptionService}:
 * these transitions run unattended off the scheduler rather than in response
 * to a staff action, so they live in their own class. Both logs with
 * `causer: null` + `ActivityContext::Scheduler`, same as every other
 * scheduled sweep in this app.
 *
 * Both methods process matching tickets in chunks of up to 500. Only ids are
 * scanned up front (never a live re-querying `chunkById()`, since the
 * chunk's own work would otherwise mutate the very columns the filter
 * matches on and skip/duplicate rows mid-sweep — the same snapshot-first
 * reasoning `SubscriptionLifecycleService` documents); full rows are loaded
 * one chunk at a time, right before they're needed, so a large backlog
 * never sits fully in memory at once. Auto-close additionally re-checks its
 * own eligibility conditions fresh, per chunk, immediately before the
 * update — closing a ticket is keyed off a live "still eligible" query, not
 * blind trust in ids collected during the original scan, so a ticket that
 * got a reply in between isn't wrongly closed. Each chunk writes a single
 * bulk `ActivityLogger` entry (covering only what actually succeeded, even
 * under partial failure) rather than one per ticket, mirroring the
 * bulk-action logging already used by `Tickets/Index::executeBulkClose()`
 * etc.
 */
class TicketLifecycleService
{
    private const CHUNK_SIZE = 500;

    /**
     * Auto-closes every Pending/Resolved ticket whose last staff message is
     * at least $inactiveDays old and hasn't had a requester reply since —
     * i.e. the staff had the last word and the requester never came back.
     * Leaves a system note in the thread and emails the requester. Returns
     * how many tickets were closed.
     */
    public function autoCloseInactive(int $inactiveDays): int
    {
        $ticketIds = $this->eligibleForAutoCloseQuery($inactiveDays)->pluck('id');

        $ticketIds->chunk(self::CHUNK_SIZE)->each(
            fn (Collection $chunk) => $this->closeChunk($chunk->values(), $inactiveDays),
        );

        return $ticketIds->count();
    }

    protected function eligibleForAutoCloseQuery(int $inactiveDays): Builder
    {
        return $this->applyAutoCloseEligibility(Ticket::query(), $inactiveDays);
    }

    /**
     * The shared eligibility filter for auto-close — used both for the initial
     * candidate scan and again, reapplied fresh, right before each chunk's
     * update. Re-applying it at update time (rather than trusting the ids
     * plucked earlier) is what stops a ticket that received a fresh user
     * reply in the gap between the scan and the update from being wrongly
     * closed.
     */
    protected function applyAutoCloseEligibility(Builder $query, int $inactiveDays): Builder
    {
        $threshold = now()->subDays($inactiveDays);

        return $query
            ->whereIn('status', [TicketStatus::Pending->value, TicketStatus::Resolved->value])
            ->whereNotNull('last_staff_response_at')
            ->where('last_staff_response_at', '<=', $threshold)
            ->where(fn (Builder $q) => $q->whereNull('last_user_response_at')
                ->orWhereColumn('last_user_response_at', '<', 'last_staff_response_at'));
    }

    /** @param  Collection<int, int>  $ticketIds */
    protected function closeChunk(Collection $ticketIds, int $inactiveDays): void
    {
        $note = "Automatically closed after {$inactiveDays} days of inactivity.";
        $now = now();

        /** @var Collection<int, int> $closedIds */
        $closedIds = DB::transaction(function () use ($ticketIds, $inactiveDays, $note, $now): Collection {
            // Re-check eligibility against this chunk's own ids, immediately before
            // updating — a ticket may have received a user reply since the original
            // scan that plucked $ticketIds.
            $closedIds = $this->applyAutoCloseEligibility(Ticket::whereIn('id', $ticketIds), $inactiveDays)
                ->pluck('id');

            if ($closedIds->isEmpty()) {
                return $closedIds;
            }

            // The update's own WHERE clause reapplies the same eligibility
            // conditions (not just whereIn(id)) so the row only closes if it's
            // still genuinely eligible at the moment of the write.
            $this->applyAutoCloseEligibility(Ticket::whereIn('id', $closedIds), $inactiveDays)->update([
                'status' => TicketStatus::Closed->value,
                'closed_at' => $now,
            ]);

            // A plain bulk insert, not $ticket->messages()->create() per row — these are
            // uniform system notes with no side effects tied to their own creation, so
            // there's nothing lost by bypassing Eloquent events here (unlike deleting a
            // Ticket itself, which the model's `deleting` event depends on).
            TicketMessage::insert($closedIds->map(fn (int $id): array => [
                'ticket_id' => $id,
                'user_id' => null,
                'author_type' => TicketMessageAuthorType::System->value,
                'message' => $note,
                'attachments' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            return $closedIds;
        });

        if ($closedIds->isEmpty()) {
            return;
        }

        // Emails are inherently per-recipient — the only unavoidable per-row work left.
        Ticket::whereIn('id', $closedIds)->with('user')->get()->each(
            fn (Ticket $ticket) => $ticket->user?->notify(new TicketAutoClosedNotification($ticket, $inactiveDays)),
        );

        ActivityLogger::log(ActivityModule::Ticket, ActivityAction::Updated, null, [
            'bulk' => true,
            'type' => 'ticket_auto_closed',
            'reason' => 'inactivity',
            'inactive_days' => $inactiveDays,
            'ticket_ids' => $closedIds->all(),
            'count' => $closedIds->count(),
        ], causer: null, context: ActivityContext::Scheduler);
    }

    /**
     * Permanently deletes every ticket that's been Closed for at least
     * $months — `Ticket::delete()` (via its `deleting` model event) removes
     * the ticket's whole attachments folder first, then the DB-level FK
     * cascade removes every `ticket_messages` row, then the ticket row
     * itself goes. Returns how many tickets were purged.
     */
    public function purgeClosedTickets(int $months): int
    {
        $threshold = now()->subMonths($months);

        // Only ids are held across the whole scan — full rows (with their
        // user/category relations) are loaded one chunk at a time, right
        // before purgeChunk() needs them, so purging a large backlog doesn't
        // eager-load every matching ticket into memory up front.
        $ticketIds = Ticket::query()
            ->where('status', TicketStatus::Closed->value)
            ->whereNotNull('closed_at')
            ->where('closed_at', '<=', $threshold)
            ->pluck('id');

        $ticketIds->chunk(self::CHUNK_SIZE)->each(function (Collection $idChunk) use ($months): void {
            $tickets = Ticket::whereIn('id', $idChunk)->with('user', 'category')->get();

            $this->purgeChunk($tickets, $months);
        });

        return $ticketIds->count();
    }

    /** @param  Collection<int, Ticket>  $tickets */
    protected function purgeChunk(Collection $tickets, int $months): void
    {
        // Snapshot per ticket as it's actually deleted, not before — if delete()
        // throws partway through the chunk, the `finally` below still logs
        // exactly what succeeded rather than the full chunk it was asked to purge.
        $purged = [];

        try {
            foreach ($tickets as $ticket) {
                // Each delete() individually fires the `deleting` event (attachments
                // cleanup) — a single bulk `whereIn(...)->delete()` query would skip
                // model events entirely and leak every ticket's storage folder.
                $ticket->delete();

                $purged[] = [
                    'id' => $ticket->id,
                    'subject' => $ticket->subject,
                    'requester' => $ticket->user?->name,
                    'category' => $ticket->category?->name,
                ];
            }
        } finally {
            // Spatie's subject reference is a logical (class + id) pointer, not an
            // FK, so it would survive individual rows being gone, but a bulk log
            // has no single subject; this snapshot is all the audit trail has left
            // to show once the rows are deleted. Logged even on partial failure
            // (the exception still propagates after this) so the count always
            // reflects what actually got purged, never what was merely attempted.
            if ($purged !== []) {
                ActivityLogger::log(ActivityModule::Ticket, ActivityAction::Deleted, null, [
                    'bulk' => true,
                    'type' => 'ticket_purged',
                    'reason' => 'retention_expired',
                    'closed_months_ago' => $months,
                    'count' => count($purged),
                    'tickets' => $purged,
                ], causer: null, context: ActivityContext::Scheduler);
            }
        }
    }
}
