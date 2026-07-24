<?php

namespace App\Enum;

use App\Models\Notification;
use Illuminate\Support\Str;

/**
 * The category a {@see Notification} broadcast was tagged with. Closed
 * vocabulary — adding a new type is a code change.
 */
enum NotificationType: string
{
    case General = 'general';
    case Announcement = 'announcement';
    case Promotional = 'promotional';
    case Alert = 'alert';

    public function label(): string
    {
        return Str::headline($this->value);
    }
}
