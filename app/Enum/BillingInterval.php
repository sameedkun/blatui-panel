<?php

namespace App\Enum;

use App\Models\PlanPrice;
use Illuminate\Support\Str;

/**
 * The unit a {@see PlanPrice}'s billing/trial/grace period is measured in.
 * Closed vocabulary — adding a new interval is a code change.
 */
enum BillingInterval: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    public function label(): string
    {
        return Str::headline($this->value);
    }
}
