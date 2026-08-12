<?php

namespace App\Support\Dashboard\Concerns;

use App\Support\Dashboard\DateRange;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Turns a query into a gap-free, chart-ready series of [label => count] buckets.
 *
 * SQL only returns rows for periods that actually have data, so a quiet Tuesday simply
 * goes missing — which would make a line chart silently skip that day and misrepresent
 * the shape of the trend. Every method here pre-seeds the full bucket range at zero and
 * merges the query's rows on top, so the x-axis is always continuous.
 */
trait BuildsTimeSeries
{
    /**
     * Bucket a query by its date column across the whole range.
     *
     * @return array<string, int>
     */
    protected function countByPeriod(Builder $query, DateRange $range, string $column = 'created_at'): array
    {
        $rows = $query
            ->whereBetween($column, [$range->start(), $range->end()])
            ->groupBy('bucket')
            ->select([
                DB::raw($this->dateBucketExpression($column, $range).' as bucket'),
                DB::raw('COUNT(*) as aggregate'),
            ])
            ->pluck('aggregate', 'bucket')
            ->all();

        return $this->fillBuckets($range, $rows);
    }

    /**
     * Bucket a query by its date column, summing a numeric column rather than counting rows.
     *
     * @return array<string, float>
     */
    protected function sumByPeriod(Builder $query, DateRange $range, string $sumColumn, string $column = 'created_at'): array
    {
        $rows = $query
            ->whereBetween($column, [$range->start(), $range->end()])
            ->groupBy('bucket')
            ->select([
                DB::raw($this->dateBucketExpression($column, $range).' as bucket'),
                DB::raw("SUM({$sumColumn}) as aggregate"),
            ])
            ->pluck('aggregate', 'bucket')
            ->map(fn ($value): float => (float) $value)
            ->all();

        return $this->fillBuckets($range, $rows, 0.0);
    }

    /**
     * Seed every bucket in the range, then overlay whatever the query returned.
     *
     * @param  array<string, mixed>  $rows
     * @return array<string, mixed>
     */
    protected function fillBuckets(DateRange $range, array $rows, int|float $default = 0): array
    {
        $buckets = [];
        $cursor = $range->start();
        $step = $range->grouping();

        for ($i = 0; $i < $range->buckets(); $i++) {
            $buckets[$this->bucketKey($cursor, $range)] = $default;
            $cursor = $step === 'month' ? $cursor->addMonth() : $cursor->addDay();
        }

        foreach ($rows as $key => $value) {
            // Only keep keys the range actually covers — a stray row from a boundary
            // rounding difference must never widen the axis.
            if (array_key_exists($key, $buckets)) {
                $buckets[$key] = $value;
            }
        }

        return $buckets;
    }

    protected function bucketKey(CarbonInterface $date, DateRange $range): string
    {
        return $range->grouping() === 'month' ? $date->format('Y-m') : $date->format('Y-m-d');
    }

    /**
     * A SQL expression formatting a datetime column down to its bucket key.
     *
     * Date formatting is one of the least portable corners of SQL — MySQL's `DATE_FORMAT`
     * does not exist on SQLite, which the test suite runs on. Emitting the right function
     * per driver keeps the aggregation in the database (rather than pulling every row into
     * PHP to group it) without tying the dashboard to one engine.
     */
    protected function dateBucketExpression(string $column, DateRange $range): string
    {
        $mask = $range->sqlFormat();

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('{$mask}', {$column})",
            'pgsql' => sprintf(
                "to_char(%s, '%s')",
                $column,
                $range->grouping() === 'month' ? 'YYYY-MM' : 'YYYY-MM-DD',
            ),
            default => "DATE_FORMAT({$column}, '{$mask}')",
        };
    }

    /**
     * A SQL expression giving the whole minutes between two datetime expressions.
     *
     * Same portability problem as {@see dateBucketExpression()} — `TIMESTAMPDIFF` is
     * MySQL-only, and SQLite has to go through Julian days instead.
     */
    protected function minutesBetweenExpression(string $from, string $to): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST((julianday({$to}) - julianday({$from})) * 1440 AS INTEGER)",
            'pgsql' => "EXTRACT(EPOCH FROM ({$to} - {$from})) / 60",
            default => "TIMESTAMPDIFF(MINUTE, {$from}, {$to})",
        };
    }

    /**
     * Human-facing axis labels for a bucket set.
     *
     * @param  array<string, mixed>  $buckets
     * @return array<int, string>
     */
    protected function bucketLabels(array $buckets, DateRange $range): array
    {
        return collect(array_keys($buckets))
            ->map(function (string $key) use ($range): string {
                return $range->grouping() === 'month'
                    ? Date::createFromFormat('Y-m-d', $key.'-01')->format('M Y')
                    : Date::createFromFormat('Y-m-d', $key)->format('M j');
            })
            ->all();
    }

    /**
     * Percentage change between two periods.
     *
     * Growth from zero has no meaningful percentage — returns null so the UI can render
     * a dash instead of a misleading "+100%".
     */
    protected function percentChange(int|float $current, int|float $previous): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
