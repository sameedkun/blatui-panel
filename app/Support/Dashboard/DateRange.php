<?php

namespace App\Support\Dashboard;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * The time window every dashboard number is scoped to.
 *
 * A range is either a named preset (the pickers on Overview/Analytics) or an
 * explicit custom window (reports). Every trend compares a range against
 * {@see previous()} — the equally long window immediately before it — so a
 * "30 days" delta is always measured against the preceding 30 days, never
 * something else. Keeping that derivation here (rather than in each metric)
 * is what makes every period-over-period figure on the dashboard agree.
 */
final class DateRange
{
    public const string DEFAULT = '30d';

    public const string CUSTOM = 'custom';

    /** @var list<string> */
    public const array PRESETS = ['7d', '30d', '90d', '12m'];

    /** Windows longer than this are charted per month instead of per day. */
    private const int DAILY_BUCKET_LIMIT = 92;

    private function __construct(
        public readonly string $key,
        public readonly CarbonInterface $start,
        public readonly CarbonInterface $end,
    ) {}

    /**
     * A named preset ending now. Unknown keys fall back to {@see DEFAULT}.
     *
     * Day presets include today as their last bucket, so "7 days" is today plus
     * the six full days before it; "12 months" is the current month plus the
     * eleven before it.
     */
    public static function preset(?string $key, ?CarbonInterface $now = null): self
    {
        $key = in_array($key, self::PRESETS, true) ? $key : self::DEFAULT;
        $now ??= Date::now();

        $start = match ($key) {
            '7d' => $now->subDays(6)->startOfDay(),
            '30d' => $now->subDays(29)->startOfDay(),
            '90d' => $now->subDays(89)->startOfDay(),
            '12m' => $now->subMonthsNoOverflow(11)->startOfMonth(),
        };

        return new self($key, $start, $now);
    }

    /** An explicit window covering whole days from $start to $end inclusive. */
    public static function between(CarbonInterface $start, CarbonInterface $end): self
    {
        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        return new self(self::CUSTOM, $start->startOfDay(), $end->endOfDay());
    }

    /** A window between two exact moments (no day snapping) — "the last 24 hours". */
    public static function exact(CarbonInterface $start, CarbonInterface $end): self
    {
        return new self(self::CUSTOM, $start, $end);
    }

    public static function isPreset(?string $key): bool
    {
        return in_array($key, self::PRESETS, true);
    }

    /** @return array<string, string> preset key => translated label, for range pickers */
    public static function options(): array
    {
        return collect(self::PRESETS)
            ->mapWithKeys(fn (string $key): array => [$key => __("dashboard.ranges.{$key}")])
            ->all();
    }

    public function label(): string
    {
        if ($this->key !== self::CUSTOM) {
            return __("dashboard.ranges.{$this->key}");
        }

        return $this->start->isSameDay($this->end)
            ? $this->start->translatedFormat('M j, Y')
            : $this->start->translatedFormat('M j, Y').' – '.$this->end->translatedFormat('M j, Y');
    }

    /**
     * The equally long window immediately preceding this one.
     *
     * Derived from the exact elapsed duration (not a day count), so a preset
     * whose last day is still in progress is compared against a window of the
     * same length rather than one that is up to a day longer.
     */
    public function previous(): self
    {
        $seconds = max(1, (int) $this->start->diffInSeconds($this->end));

        return new self("{$this->key}:previous", $this->start->subSeconds($seconds), $this->start->subSecond());
    }

    /** Whole days the window touches. */
    public function days(): int
    {
        return (int) $this->start->startOfDay()->diffInDays($this->end->startOfDay()) + 1;
    }

    /** `day` or `month` — the resolution time-series charts are bucketed at. */
    public function granularity(): string
    {
        return $this->days() > self::DAILY_BUCKET_LIMIT ? 'month' : 'day';
    }

    /** The bucket key a moment falls into (matches {@see TimeSeries}' SQL keys). */
    public function bucketKey(CarbonInterface $date): string
    {
        return $this->granularity() === 'month' ? $date->format('Y-m') : $date->format('Y-m-d');
    }

    /**
     * Every bucket in the window, oldest first.
     *
     * @return array<string, string> bucket key => axis label
     */
    public function buckets(): array
    {
        $monthly = $this->granularity() === 'month';
        $cursor = $monthly ? $this->start->startOfMonth() : $this->start->startOfDay();
        $buckets = [];

        while ($cursor->lessThanOrEqualTo($this->end)) {
            $buckets[$this->bucketKey($cursor)] = $monthly ? $cursor->translatedFormat('M Y') : $cursor->translatedFormat('M j');
            $cursor = $monthly ? $cursor->addMonthNoOverflow() : $cursor->addDay();
        }

        return $buckets;
    }

    /** A stable identity for cache keys — presets by name, custom windows by date. */
    public function cacheKey(): string
    {
        return $this->key === self::CUSTOM
            ? 'custom:'.$this->start->format('Ymd').'-'.$this->end->format('Ymd')
            : $this->key;
    }
}
