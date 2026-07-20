<?php

namespace App\Enum;

use App\Models\EmailSender;
use Illuminate\Support\Str;

/**
 * Fixed set of mail "purposes" an {@see EmailSender} row can be
 * assigned to. Closed vocabulary (not admin-extensible) — adding a new
 * purpose is a code change, mirroring the rest of the app's per-axis enums.
 */
enum MailPurpose: string
{
    case Default = 'default';
    case Auth = 'auth';
    case Notifications = 'notifications';
    case Support = 'support';
    case Billing = 'billing';
    case Marketing = 'marketing';

    public function label(): string
    {
        return Str::headline($this->value);
    }
}
