<?php

namespace App\Enum;

use App\Models\Announcement;

/**
 * The category an {@see Announcement} broadcast was tagged with. Closed
 * vocabulary — adding a new type is a code change.
 */
enum AnnouncementType: string
{
    case General = 'general';
    case Announcement = 'announcement';
    case Promotional = 'promotional';
    case Alert = 'alert';

    public function label(): string
    {
        return __("enums.announcement_type.{$this->name}");
    }
}
