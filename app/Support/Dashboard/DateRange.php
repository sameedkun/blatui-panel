<?php

namespace App\Support\Dashboard;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * The dashboard's global time window.
 *
 * Every metric on the page is scoped by one of these, and every trend figure compares
 * the window against the equally-sized window immediately before it. Keeping that
 * "previous window" derivation here (rather than in each metric) is what guarantees a
 * 30-day delta is always measured against the preceding 30 days and never something else.
 */
enum DateRange: string
{
    case Week = '7d';
    case Month = '30d';
    case Quarter = '90d';
    case Year = '12mo';

    /** Number of whole days the window spans. */
    public function days(): int
    {
        return match ($this) {
            self::Week => 7,
            self::Month => 30,
            self::Quarter => 90,
            self::Year => 365,
        };
    }

    public function label(): string
    {
        return __('dashboard.ranges.'.$this->value);
    }

    /** Start of the current window. */
    public function start(): CarbonInterface
    {
        return Date::now()->subDays($this->days())->startOfDay();
    }

    public function end(): CarbonInterface
    {
        return Date::now();
    }

    /** Start of the equally-sized window immediately preceding this one. */
    public function previousStart(): CarbonInterface
    {
        return Date::now()->subDays($this->days() * 2)->startOfDay();
    }

    /** End of the previous window — the instant the current one begins. */
    public function previousEnd(): CarbonInterface
    {
        return $this->start();
    }

    /**
     * Grouping granularity for time-series charts.
     *
     * Short windows are plotted per day; a year of daily points would be unreadable
     * (and 365 SQL groups), so it buckets by month instead.
     */
    public function grouping(): string
    {
        return $this === self::Year ? 'month' : 'day';
    }

    /** MySQL DATE_FORMAT mask matching {@see grouping()}. */
    public function sqlFormat(): string
    {
        return $this->grouping() === 'month' ? '%Y-%m' : '%Y-%m-%d';
    }

    /** Number of buckets a full series for this range contains. */
    public function buckets(): int
    {
        return $this->grouping() === 'month' ? 12 : $this->days();
    }

    public static function fromValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Month;
    }

    /** @return array<string, string> value => label, for the range selector */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
