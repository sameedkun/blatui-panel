<?php

namespace App\Enum;

use App\Models\Feedback;

/**
 * The triage state of a {@see Feedback} submission. Closed vocabulary —
 * adding a new status is a code change.
 */
enum FeedbackStatus: string
{
    case New = 'new';
    case Read = 'read';
    case Resolved = 'resolved';
    case Ignored = 'ignored';

    public function label(): string
    {
        return __("enums.feedback_status.{$this->name}");
    }
}
