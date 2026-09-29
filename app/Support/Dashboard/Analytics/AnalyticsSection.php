<?php

namespace App\Support\Dashboard\Analytics;

use App\Support\Dashboard\Blocks\Row;
use App\Support\Dashboard\DateRange;

/**
 * One tab of the Analytics page.
 *
 * A section is a self-contained description of a domain: its tab metadata,
 * the permission that gates it, and a build() that turns a date range into
 * rows of blocks. The page renders any section generically, so an
 * application adds a tab by writing one of these and listing it in
 * `config/dashboard.php` — no Livewire or Blade changes.
 *
 * Convention for a section's layout (the visual hierarchy every tab shares):
 * KPI strip → primary chart → breakdowns → detailed tables.
 */
abstract class AnalyticsSection
{
    /** URL-safe tab key (`?tab=`). */
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function description(): string;

    /** Lucide icon name, without the `lucide-` prefix. */
    abstract public function icon(): string;

    /** @return list<Row> */
    abstract public function build(DateRange $range): array;

    /** Gate ability required to open this tab, or null for any analytics viewer. */
    public function permission(): ?string
    {
        return null;
    }

    /**
     * Whether this section can answer the given range at all — e.g. a section
     * backed by rollups that only exist for some windows.
     */
    public function supports(DateRange $range): bool
    {
        return true;
    }
}
