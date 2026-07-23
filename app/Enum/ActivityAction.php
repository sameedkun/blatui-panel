<?php

namespace App\Enum;

/**
 * Axis 3 — reusable verbs shared across every module (stored in Spatie's `event`
 * column). Keep these generic: `Updated`, never `UpdatedEmail`. No module-specific
 * actions, no event explosion — distinguishing detail lives in `properties`.
 */
enum ActivityAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case ForceDeleted = 'force_deleted';
    case Banned = 'banned';
    case Unbanned = 'unbanned';
    case Assigned = 'assigned';
    case Login = 'login';
    case Failed = 'failed';
    case PasswordReset = 'password_reset';
    case Verified = 'verified';
    case DeletionRequested = 'deletion_requested';
    case DeletionCancelled = 'deletion_cancelled';
    case Purged = 'purged';
    case Converted = 'converted';
    case Merged = 'merged';
    case Sent = 'sent';
    case Cancelled = 'cancelled';
}
