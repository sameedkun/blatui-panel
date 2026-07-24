<?php

namespace App\Enum;

use App\Models\Feedback;
use Illuminate\Support\Str;

/**
 * The category a {@see Feedback} submission was tagged with. Closed
 * vocabulary — adding a new type is a code change.
 */
enum FeedbackType: string
{
    case General = 'general';
    case Bug = 'bug';
    case Feature = 'feature';
    case Complaint = 'complaint';
    case Other = 'other';

    public function label(): string
    {
        return Str::headline($this->value);
    }
}
