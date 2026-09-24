<?php

declare(strict_types=1);

namespace App\Support\ApiLogs;

use App\Enum\ApiStatsPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * One selectable window on the API analytics page: how far back it reaches,
 * which storage tier answers it (raw logs for up to a day, then hour/day/
 * month rollups), and how its charts are bucketed. Short windows chart at
 * minute-level resolution straight from raw logs; longer ones at the
 * granularity their rollup tier already has.
 */
final class AnalyticsRange
{
    public const string DEFAULT = '24h';

    /** @var list<string> */
    public const array KEYS = ['1h', '6h', '24h', '7d', '30d', '90d', '1y', 'all'];

    private function __construct(
        public readonly string $key,
        public readonly CarbonInterface $start,
        public readonly CarbonInterface $end,
    ) {}

    /**
     * @param  CarbonInterface|null  $allTimeStart  Where "all" begins (the oldest monthly rollup); defaults to a year back.
     */
    public static function fromKey(?string $key, ?CarbonInterface $now = null, ?CarbonInterface $allTimeStart = null): self
    {
        $key = in_array($key, self::KEYS, true) ? $key : self::DEFAULT;
        $now ??= Date::now();

        $start = match ($key) {
            '1h' => $now->subHour(),
            '6h' => $now->subHours(6),
            '24h' => $now->subDay(),
            '7d' => $now->subDays(7),
            '30d' => $now->subDays(30),
            '90d' => $now->subDays(90),
            '1y' => $now->subYear(),
            'all' => ($allTimeStart !== null && $allTimeStart->lessThan($now->subYear())) ? $allTimeStart->startOfMonth() : $now->subYear(),
        };

        return new self($key, $start, $now);
    }

    /** @return array<string, string> key => translated label, for the range picker */
    public static function options(): array
    {
        return collect(self::KEYS)->mapWithKeys(fn (string $key): array => [$key => __("api_logs.ranges.{$key}")])->all();
    }

    public function label(): string
    {
        return __("api_logs.ranges.{$this->key}");
    }

    /** The rollup tier that answers this range, or null for raw logs. */
    public function source(): ?ApiStatsPeriod
    {
        return match ($this->key) {
            '1h', '6h', '24h' => null,
            '7d' => ApiStatsPeriod::Hour,
            '30d', '90d', '1y' => ApiStatsPeriod::Day,
            'all' => ApiStatsPeriod::Month,
        };
    }

    /**
     * Whether raw logs still cover the whole range — metrics that only exist on
     * raw rows (error codes, IPs, users, client types) are offered only then.
     */
    public function withinRawRetention(): bool
    {
        return $this->start->greaterThanOrEqualTo($this->end->subDays((int) config('api_logs.retention.raw_days', 7))->subMinute());
    }

    /** The live window auto-refreshes. */
    public function isLive(): bool
    {
        return $this->key === '1h';
    }

    public function cacheSeconds(): int
    {
        return match ($this->key) {
            '1h' => 10,
            '6h', '24h' => 30,
            default => 300,
        };
    }

    public function minutes(): float
    {
        return max(1.0, $this->start->diffInMinutes($this->end));
    }

    /** The chart bucket a timestamp falls in, as a sortable key. */
    public function bucketKey(CarbonInterface $at): string
    {
        return match ($this->chartUnit()) {
            'day' => $at->format('Y-m-d'),
            'month' => $at->format('Y-m'),
            default => Date::createFromTimestamp(
                intdiv($at->getTimestamp(), $this->stepSeconds()) * $this->stepSeconds(),
                $at->getTimezone(),
            )->format('Y-m-d H:i'),
        };
    }

    /**
     * Every chart bucket in the range, oldest first — pre-seeded so a quiet
     * period shows as zero instead of vanishing from the x-axis.
     *
     * @return array<string, string> bucket key => axis label
     */
    public function buckets(): array
    {
        $buckets = [];
        $cursor = $this->bucketStart($this->start);

        while ($cursor->lessThanOrEqualTo($this->end)) {
            $buckets[$this->bucketKey($cursor)] = $this->axisLabel($cursor);

            $cursor = match ($this->chartUnit()) {
                'day' => $cursor->addDay(),
                'month' => $cursor->addMonthNoOverflow(),
                default => $cursor->addSeconds($this->stepSeconds()),
            };
        }

        return $buckets;
    }

    private function bucketStart(CarbonInterface $at): CarbonInterface
    {
        return match ($this->chartUnit()) {
            'day' => $at->startOfDay(),
            'month' => $at->startOfMonth(),
            default => Date::createFromTimestamp(intdiv($at->getTimestamp(), $this->stepSeconds()) * $this->stepSeconds(), $at->getTimezone()),
        };
    }

    private function axisLabel(CarbonInterface $at): string
    {
        return match ($this->chartUnit()) {
            'day' => $at->translatedFormat('M j'),
            'month' => $at->translatedFormat('M Y'),
            default => $this->key === '7d' ? $at->translatedFormat('D H:i') : $at->format('H:i'),
        };
    }

    /** 'seconds' (fixed-width buckets of stepSeconds()), 'day' or 'month'. */
    private function chartUnit(): string
    {
        return match ($this->key) {
            '30d', '90d' => 'day',
            '1y', 'all' => 'month',
            default => 'seconds',
        };
    }

    private function stepSeconds(): int
    {
        return match ($this->key) {
            '1h' => 60,
            '6h' => 300,
            '24h' => 1800,
            default => 3600,
        };
    }
}
