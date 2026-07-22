<?php

namespace App\Enum;

use App\Models\Subscription;
use Illuminate\Support\Str;

/**
 * Who cancelled a {@see Subscription}. Closed vocabulary — adding a new actor
 * is a code change.
 */
enum CancelledBy: string
{
    case User = 'user';
    case System = 'system';

    public function label(): string
    {
        return Str::headline($this->value);
    }
}
