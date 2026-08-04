<?php

namespace App\Services\Ticket;

use App\Enum\TicketStatus;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Picks who should handle the next ticket in a category — pure selection,
 * no side effects. {@see TicketService} is what actually applies the pick
 * and writes the audit trail, so this stays reusable from any call site
 * (initial creation, category change, a future "rebalance" action) without
 * risking a double-logged assignment.
 *
 * Also the single source of truth for what makes a staff member an eligible
 * "agent" at all — every place in the panel that offers or accepts an agent
 * (the category form's checklist, auto-assignment, manual reassignment, the
 * bulk-assign dialog, the ticket filters) goes through this rather than
 * re-deriving the permission check.
 */
class AssignmentService
{
    /**
     * Staff who can actually view and reply to tickets — the only ones
     * eligible to be an agent. Chainable, so callers can add ordering,
     * further scoping, or aggregate a category's pool against it.
     */
    public function eligibleAgentsQuery(): Builder
    {
        return $this->scopeToEligible(User::staff());
    }

    /**
     * @return Collection<int, User>
     */
    public function eligibleAgents(): Collection
    {
        return $this->eligibleAgentsQuery()->orderBy('name')->get(['id', 'name', 'email']);
    }

    /**
     * @return array<int, string>
     */
    public function eligibleAgentOptions(): array
    {
        return $this->eligibleAgentsQuery()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * The category's least-loaded eligible agent — "load" being how many of
     * their tickets are still open (not Resolved/Closed). Ties break on id
     * for determinism. Returns null when the category has no eligible agents
     * attached (either none configured, or none left with ticket access).
     */
    public function pickAgent(TicketCategory $category): ?User
    {
        return $this->scopeToEligible($category->agents())
            ->withCount(['assignedTickets as open_ticket_load' => function ($query): void {
                $query->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value]);
            }])
            ->orderBy('open_ticket_load')
            ->orderBy('users.id')
            ->first();
    }

    private function scopeToEligible(Builder|BelongsToMany $query): Builder|BelongsToMany
    {
        return $query->permission('tickets.view')->permission('tickets.manage');
    }
}
