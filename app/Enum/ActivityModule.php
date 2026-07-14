<?php

namespace App\Enum;

/**
 * Axis 2 — the feature/module an activity belongs to. Grows freely as modules
 * are added. Stored in the activity's `properties.module` (the canonical store)
 * and is also inferable from `subject_type` whenever a subject exists.
 */
enum ActivityModule: string
{
    case User = 'user';
    case Guest = 'guest';
    case Staff = 'staff';
    case Role = 'role';
    case Permission = 'permission';
    case Plan = 'plan';
    case Server = 'server';
    case Ticket = 'ticket';
}
