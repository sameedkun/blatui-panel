<?php

namespace App\Enum;

use App\Models\Ticket;
use Illuminate\Support\Str;

/**
 * The lifecycle state of a {@see Ticket}. Closed vocabulary — adding a new
 * state is a code change.
 *
 * Open     — newly created or reopened, awaiting a staff response.
 * Pending  — staff has responded, waiting on the customer.
 * Resolved — staff considers the issue solved.
 * Closed   — terminal; the ticket is archived (closed_at set).
 */
enum TicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return Str::headline($this->value);
    }

    /** Terminal states a ticket must be reopened from before it can be replied to. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Resolved, self::Closed], true);
    }
}
