<?php

namespace App\Enum;

use App\Models\Subscription;

/**
 * Who cancelled a {@see Subscription}. Closed vocabulary — adding a new actor
 * is a code change.
 */
enum CancelledBy: string
{
    case User = 'user';
    case Admin = 'admin';
    case System = 'system';

    public function label(): string
    {
        return __("enums.cancelled_by.{$this->name}");
    }
}
