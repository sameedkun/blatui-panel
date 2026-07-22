<?php

namespace App\Enum;

use App\Models\Subscription;
use Illuminate\Support\Str;

/**
 * The lifecycle state of a {@see Subscription}. Closed vocabulary — adding a
 * new status is a code change.
 */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case Grace = 'grace';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return Str::headline($this->value);
    }
}
