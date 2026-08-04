<?php

namespace App\Livewire\Admin\Support\Tickets\Concerns;

use App\Enum\TicketStatus;
use App\Livewire\Admin\Concerns\HasToast;
use App\Models\Ticket;
use App\Services\Ticket\TicketService;

/**
 * Single-ticket actions shared by the Tickets index and the ticket detail
 * page — claiming, closing, and reopening run the exact same
 * {@see TicketService} calls (and write the exact same audit rows) from
 * either surface.
 *
 * Requires the using component to also use {@see HasToast}.
 */
trait HandlesTicketRowActions
{
    public function assignToMe(int $ticketId): void
    {
        $this->authorize('tickets.manage');

        $ticket = Ticket::visibleTo(auth()->user())->findOrFail($ticketId);
        $agent = auth()->user();

        app(TicketService::class)->reassign($ticket, $agent, $agent);

        $this->toastSuccess(__('tickets.toasts.assigned_to_you', ['subject' => $ticket->subject]));
    }

    public function close(int $ticketId): void
    {
        $this->authorize('tickets.manage');

        $ticket = Ticket::visibleTo(auth()->user())->findOrFail($ticketId);

        app(TicketService::class)->changeStatus($ticket, TicketStatus::Closed, auth()->user());

        $this->toastSuccess(__('tickets.toasts.closed', ['subject' => $ticket->subject]));
    }

    public function reopen(int $ticketId): void
    {
        $this->authorize('tickets.manage');

        $ticket = Ticket::visibleTo(auth()->user())->findOrFail($ticketId);

        app(TicketService::class)->changeStatus($ticket, TicketStatus::Open, auth()->user());

        $this->toastSuccess(__('tickets.toasts.reopened', ['subject' => $ticket->subject]));
    }
}
