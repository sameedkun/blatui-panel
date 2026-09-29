<?php

namespace App\Support\Dashboard\Reports\Writers;

use App\Support\Dashboard\Reports\ReportDocument;
use RuntimeException;

/**
 * Plain CSV: a heading row, then data. UTF-8 with a BOM so Excel opens
 * non-ASCII names correctly instead of guessing a legacy code page.
 */
class CsvWriter extends ReportWriter
{
    public function write(ReportDocument $document, string $path): int
    {
        $handle = fopen($path, 'w') ?: throw new RuntimeException("Unable to open [{$path}] for writing.");
        $keys = array_keys($document->columns);
        $count = 0;

        try {
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_values($document->columns), escape: '');

            foreach ($document->rows as $row) {
                fputcsv($handle, array_map(fn (string $key): string => $this->safe($row[$key] ?? null), $keys), escape: '');
                $count++;
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }

    /**
     * Neutralise spreadsheet formula injection: a cell a user controls (a
     * name, a ticket subject) that starts with = + - @ would otherwise run as
     * a formula when the CSV is opened. Numbers are left alone.
     */
    private function safe(int|float|string|null $value): string
    {
        $text = $this->cellText($value);

        if (is_string($value) && $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$text;
        }

        return $text;
    }
}
