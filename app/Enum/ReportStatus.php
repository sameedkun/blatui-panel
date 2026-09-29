<?php

namespace App\Enum;

use App\Models\Report\GeneratedReport;

/**
 * Where a {@see GeneratedReport} is in its queued lifecycle. Closed vocabulary.
 *
 * Pending    — requested, waiting for a queue worker.
 * Processing — a worker is building the file.
 * Completed  — the file is on disk and downloadable.
 * Failed     — generation threw; `error` holds why. Can be retried.
 */
enum ReportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return __("enums.report_status.{$this->name}");
    }

    /** Still waiting on the queue — the list polls while any report is in flight. */
    public function isInFlight(): bool
    {
        return in_array($this, [self::Pending, self::Processing], true);
    }
}
