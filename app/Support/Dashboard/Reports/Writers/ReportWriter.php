<?php

namespace App\Support\Dashboard\Reports\Writers;

use App\Enum\ReportFormat;
use App\Support\Dashboard\Reports\ReportDocument;

/**
 * Writes a {@see ReportDocument} to a local file in one format.
 *
 * CSV is written natively, XLSX through OpenSpout (streamed, flat memory) and
 * PDF through dompdf (a styled Blade template with full Unicode fonts).
 * Swapping an implementation is a change to {@see self::for()} alone.
 */
abstract class ReportWriter
{
    /** Write the document to $path and return the number of data rows written. */
    abstract public function write(ReportDocument $document, string $path): int;

    public static function for(ReportFormat $format): self
    {
        return match ($format) {
            ReportFormat::Csv => new CsvWriter,
            ReportFormat::Xlsx => new XlsxWriter,
            ReportFormat::Pdf => new PdfWriter,
        };
    }

    /** Cell value as text — null as empty, floats without trailing noise. */
    protected function cellText(int|float|string|null $value): string
    {
        return match (true) {
            $value === null => '',
            is_float($value) => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.'),
            default => (string) $value,
        };
    }

    /**
     * Buffer the first $size rows (e.g. to size columns) and hand back the
     * rest as a still-lazy iterator, so a large dataset is never fully loaded.
     *
     * @param  iterable<array<string, int|float|string|null>>  $rows
     * @return array{0: list<array<string, int|float|string|null>>, 1: iterable<array<string, int|float|string|null>>}
     */
    protected function sample(iterable $rows, int $size): array
    {
        $iterator = (function () use ($rows) {
            yield from $rows;
        })();
        $sample = [];

        while ($iterator->valid() && count($sample) < $size) {
            $sample[] = $iterator->current();
            $iterator->next();
        }

        $rest = (function () use ($iterator) {
            while ($iterator->valid()) {
                yield $iterator->current();
                $iterator->next();
            }
        })();

        return [$sample, $rest];
    }
}
