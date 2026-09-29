<?php

namespace App\Support\Dashboard\Reports;

use App\Support\Dashboard\DateRange;

/**
 * A report the panel can generate: a defined, downloadable dataset for a
 * period, independent of any page.
 *
 * Definitions are listed in `config('dashboard.reports.definitions')`; the
 * Reports page, the queue job and the scheduler all work from this contract
 * alone, so an application adds a report (VPN usage, device inventory, …)
 * by writing one class. Rows are yielded lazily — a definition should use
 * lazyById()/cursor() so a year of data never sits in memory at once.
 */
abstract class ReportDefinition
{
    /** Stable key stored on generated/scheduled rows. Never rename one in use. */
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function description(): string;

    /** Lucide icon name, without the `lucide-` prefix. */
    abstract public function icon(): string;

    /**
     * Column key => heading, in output order.
     *
     * @return array<string, string>
     */
    abstract public function columns(): array;

    /**
     * The dataset, one associative array per row keyed like {@see columns()}.
     *
     * @param  array<string, string>  $filters  already normalised by {@see normalizeFilters()}
     * @return iterable<array<string, int|float|string|null>>
     */
    abstract public function rows(DateRange $range, array $filters): iterable;

    /** Gate ability required to generate or download this report. */
    public function permission(): ?string
    {
        return null;
    }

    /** @return list<ReportFilter> */
    public function filters(): array
    {
        return [];
    }

    /**
     * Headline figures printed above the table (PDF) / shown with the report.
     *
     * @param  array<string, string>  $filters
     * @return array<string, string> label => formatted value
     */
    public function summary(DateRange $range, array $filters): array
    {
        return [];
    }

    /**
     * Keep only known filters with valid values — anything else means "All".
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public function normalizeFilters(array $input): array
    {
        $filters = [];

        foreach ($this->filters() as $filter) {
            $value = $input[$filter->key] ?? null;

            if ($value !== null && $value !== '' && $filter->accepts($value)) {
                $filters[$filter->key] = (string) $value;
            }
        }

        return $filters;
    }

    /**
     * Human description of the applied filters, for report headers.
     *
     * @param  array<string, string>  $filters
     * @return array<string, string> filter label => option label
     */
    public function describeFilters(array $filters): array
    {
        $described = [];

        foreach ($this->filters() as $filter) {
            if (isset($filters[$filter->key])) {
                $described[$filter->label] = $filter->options[$filters[$filter->key]] ?? $filters[$filter->key];
            }
        }

        return $described;
    }
}
