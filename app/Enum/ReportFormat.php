<?php

namespace App\Enum;

use App\Models\Report\GeneratedReport;

/**
 * The file format a {@see GeneratedReport} is written in. Closed vocabulary —
 * each case maps to one writer in App\Support\Dashboard\Reports\Writers.
 */
enum ReportFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Pdf = 'pdf';

    public function label(): string
    {
        return __("enums.report_format.{$this->name}");
    }

    public function extension(): string
    {
        return $this->value;
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Pdf => 'application/pdf',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Csv => 'file-text',
            self::Xlsx => 'file-spreadsheet',
            self::Pdf => 'file-type',
        };
    }
}
