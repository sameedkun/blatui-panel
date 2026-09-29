<?php

namespace App\Support\Dashboard;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Turns a query into a gap-free, chart-ready series of bucket => value.
 *
 * SQL only returns rows for periods that actually had data, so a quiet day
 * would simply go missing and a line chart would silently skip it. Every
 * method here seeds the full bucket range at zero first and overlays the
 * query's rows on top, so the x-axis is always continuous and every series
 * for the same {@see DateRange} lines up index-for-index.
 *
 * Aggregation stays in the database (one grouped query per series); only the
 * date-formatting expression differs per driver.
 */
final class TimeSeries
{
    /**
     * Rows per bucket.
     *
     * @return array<string, int>
     */
    public static function count(EloquentBuilder|QueryBuilder $query, DateRange $range, string $column = 'created_at'): array
    {
        return self::aggregate($query, $range, $column, 'COUNT(*)', 0, fn ($value): int => (int) $value);
    }

    /**
     * Distinct values of $distinctColumn per bucket (e.g. unique active users per day).
     *
     * @return array<string, int>
     */
    public static function countDistinct(EloquentBuilder|QueryBuilder $query, DateRange $range, string $distinctColumn, string $column = 'created_at'): array
    {
        return self::aggregate($query, $range, $column, "COUNT(DISTINCT {$distinctColumn})", 0, fn ($value): int => (int) $value);
    }

    /**
     * Sum of a numeric column per bucket.
     *
     * @return array<string, float>
     */
    public static function sum(EloquentBuilder|QueryBuilder $query, DateRange $range, string $sumColumn, string $column = 'created_at'): array
    {
        return self::aggregate($query, $range, $column, "SUM({$sumColumn})", 0.0, fn ($value): float => round((float) $value, 2));
    }

    /**
     * Running total per bucket, starting from $base (e.g. users that existed before the range).
     *
     * @param  array<string, int|float>  $series
     * @return array<string, int|float>
     */
    public static function cumulative(array $series, int|float $base = 0): array
    {
        $running = $base;

        foreach ($series as $bucket => $value) {
            $running += $value;
            $series[$bucket] = $running;
        }

        return $series;
    }

    /**
     * Fit a previous-period series onto the current period's x-axis, index
     * for index. The two windows are equally long but can straddle a
     * different number of calendar buckets (a 30-day window with a partial
     * today touches 30 days; the one before it may touch 31), so the most
     * recent $length values are kept and a shorter series is padded at the
     * front with nulls — gaps in the line, never invented zeros.
     *
     * @param  array<string, int|float>  $previous
     * @return list<int|float|null>
     */
    public static function alignPrevious(array $previous, int $length): array
    {
        $values = array_values($previous);

        return count($values) >= $length
            ? array_slice($values, -$length)
            : [...array_fill(0, $length - count($values), null), ...$values];
    }

    /** A SQL expression formatting a datetime column down to its bucket key. */
    public static function bucketExpression(string $column, DateRange $range): string
    {
        $monthly = $range->granularity() === 'month';

        return match (DB::connection()->getDriverName()) {
            'sqlite' => sprintf("strftime('%s', %s)", $monthly ? '%Y-%m' : '%Y-%m-%d', $column),
            'pgsql' => sprintf("to_char(%s, '%s')", $column, $monthly ? 'YYYY-MM' : 'YYYY-MM-DD'),
            'sqlsrv' => sprintf('FORMAT(%s, \'%s\')', $column, $monthly ? 'yyyy-MM' : 'yyyy-MM-dd'),
            default => sprintf("DATE_FORMAT(%s, '%s')", $column, $monthly ? '%Y-%m' : '%Y-%m-%d'),
        };
    }

    /**
     * @param  callable(mixed): (int|float)  $cast
     * @return array<string, int|float>
     */
    private static function aggregate(
        BuilderContract $query,
        DateRange $range,
        string $column,
        string $aggregate,
        int|float $default,
        callable $cast,
    ): array {
        $buckets = array_fill_keys(array_keys($range->buckets()), $default);

        $rows = $query
            ->whereBetween($column, [$range->start, $range->end])
            ->reorder()
            ->groupByRaw(self::bucketExpression($column, $range))
            ->selectRaw(self::bucketExpression($column, $range).' as bucket, '.$aggregate.' as aggregate')
            ->pluck('aggregate', 'bucket');

        foreach ($rows as $bucket => $value) {
            // Only keep buckets the range covers — a boundary rounding difference
            // must never widen the axis.
            if (array_key_exists((string) $bucket, $buckets)) {
                $buckets[(string) $bucket] = $cast($value);
            }
        }

        return $buckets;
    }
}
