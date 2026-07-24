<?php

namespace App\Enum;

use App\Models\TicketMessage;
use Illuminate\Support\Str;

/**
 * Who authored a {@see TicketMessage}.
 *
 * User   — the requester (`user_id` is the requester).
 * Staff  — an agent's reply (`user_id` is the staff member).
 * System — an automated note (e.g. auto-assignment, reassignment); `user_id` is null.
 */
enum TicketMessageAuthorType: string
{
    case User = 'user';
    case Staff = 'staff';
    case System = 'system';

    public function label(): string
    {
        return Str::headline($this->value);
    }
}
