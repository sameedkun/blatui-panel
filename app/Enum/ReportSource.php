<?php

namespace App\Enum;

use App\Models\Report\GeneratedReport;

/** What produced a {@see GeneratedReport}. Closed vocabulary. */
enum ReportSource: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return __("enums.report_source.{$this->name}");
    }
}
