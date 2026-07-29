<?php

namespace App\Enum;

use App\Models\Ticket;

/**
 * The urgency of a {@see Ticket}, set on creation and adjustable by staff.
 */
enum TicketPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return __("enums.ticket_priority.{$this->name}");
    }
}
