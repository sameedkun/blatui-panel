<?php

namespace App\Enum;

use App\Support\ActivityLogger;

/**
 * Axis 4 — the runtime an activity originated from. Auto-detected by
 * {@see ActivityLogger} from the runtime (request → Admin/Api,
 * console → Console); pass an explicit value to override for a Scheduler, Queue,
 * or Webhook origin the runtime cannot infer. Stored in `properties.context`.
 */
enum ActivityContext: string
{
    case Admin = 'admin';
    case Api = 'api';
    case Scheduler = 'scheduler';
    case Queue = 'queue';
    case Console = 'console';
    case Webhook = 'webhook';
}
