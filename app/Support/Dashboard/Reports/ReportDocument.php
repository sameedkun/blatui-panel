<?php

namespace App\Support\Dashboard\Reports;

/**
 * Everything a writer needs to produce one report file, independent of
 * format: headings, the dataset, and the context printed around it.
 */
final class ReportDocument
{
    /**
     * @param  array<string, string>  $columns  key => heading
     * @param  iterable<array<string, int|float|string|null>>  $rows
     * @param  array<string, string>  $meta  label => value (period, filters, generated at)
     * @param  array<string, string>  $summary  label => formatted value
     */
    public function __construct(
        public readonly string $title,
        public readonly array $columns,
        public readonly iterable $rows,
        public readonly array $meta = [],
        public readonly array $summary = [],
    ) {}
}
