<?php

namespace App\Services;

use App\Enum\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;

/**
 * Picks who should handle the next ticket in a category — pure selection,
 * no side effects. {@see TicketService} is what actually applies the pick
 * and writes the audit trail, so this stays reusable from any call site
 * (initial creation, category change, a future "rebalance" action) without
 * risking a double-logged assignment.
 */
class TicketAssignmentService
{
    /**
     * The category's least-loaded agent — "load" being how many of their
     * tickets are still open (not Resolved/Closed). Ties break on id for
     * determinism. Returns null when the category has no agents attached.
     */
    public function pickAgent(TicketCategory $category): ?User
    {
        return $category->agents()
            ->withCount(['assignedTickets as open_ticket_load' => function ($query): void {
                $query->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value]);
            }])
            ->orderBy('open_ticket_load')
            ->orderBy('users.id')
            ->first();
    }
}
