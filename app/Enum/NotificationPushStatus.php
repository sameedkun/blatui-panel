<?php

namespace App\Enum;

use App\Jobs\SendPushNotification;
use App\Models\Notification;

/**
 * The push-delivery state of a {@see Notification}. Closed vocabulary —
 * adding a new state is a code change.
 *
 * Draft   — saved without sending; never queued.
 * Pending — queued for {@see SendPushNotification} but not yet processed.
 * Sent    — OneSignal accepted the broadcast.
 * Failed  — OneSignal rejected it or the request errored; see `push_error`.
 */
enum NotificationPushStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return __("enums.notification_push_status.{$this->name}");
    }
}
