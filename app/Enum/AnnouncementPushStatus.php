<?php

namespace App\Enum;

use App\Jobs\Announcement\SendPushNotification;
use App\Models\Announcement;

/**
 * The push-delivery state of an {@see Announcement}. Closed vocabulary —
 * adding a new state is a code change.
 *
 * Draft   — saved without sending; never queued.
 * Pending — queued for {@see SendPushNotification} but not yet processed.
 * Sent    — OneSignal accepted the broadcast.
 * Failed  — OneSignal rejected it or the request errored; see `push_error`.
 */
enum AnnouncementPushStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return __("enums.announcement_push_status.{$this->name}");
    }
}
