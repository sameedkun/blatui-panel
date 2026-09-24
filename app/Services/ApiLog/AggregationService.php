<?php

namespace App\Services\ApiLog;

use App\Enum\ApiStatsPeriod;
use App\Models\ApiLog\ApiRequestStat;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Builds api_request_stats: raw request logs → hour rows → day rows → month
 * rows. Each bucket is rebuilt wholesale (delete + insert in one transaction)
 * from its source, so running any step twice gives the same result, and a
 * bucket whose source has since been pruned is simply left alone (it's only
 * ever touched when source rows for it exist).
 *
 * Scheduled runs re-walk a short trailing window of recent buckets on top of
 * anything newer than the last stored one — cheap, since the walk jumps
 * straight between buckets that actually have source rows — so requests
 * flushed late (a backed-up buffer) still land in their hour. Pure,
 * driver-portable SQL: a bucket's bounds are passed as parameters rather than
 * computed with date functions, which differ between MySQL and SQLite.
 */
class AggregationService
{
    /** Buckets rebuilt per period per scheduled run, so a long catch-up is spread over runs. */
    private const int MAX_BUCKETS_PER_RUN = 24 * 14;

    /** Recent buckets re-walked every scheduled run, per period. */
    private const int TRAILING_BUCKETS = 3;

    private const string BUCKET_FORMAT = 'Y-m-d H:i:s';

    /**
     * The scheduled entry point: every complete hour, day and month that
     * hasn't been rolled up yet (plus the trailing window).
     *
     * @return array{hour: int, day: int, month: int} buckets rebuilt per period
     */
    public function aggregate(?CarbonInterface $now = null): array
    {
        $now ??= Date::now();
        $rebuilt = [];

        foreach (ApiStatsPeriod::cases() as $period) {
            $lastComplete = $this->previous($period, $period->floor($now));
            $from = $this->scheduledStart($period, $lastComplete);

            $rebuilt[$period->value] = $from === null
                ? 0
                : $this->rebuild($period, $from, $lastComplete, self::MAX_BUCKETS_PER_RUN);
        }

        return $rebuilt;
    }

    /**
     * Rebuild every complete bucket between two dates, uncapped (backfills via
     * `api-logs:aggregate --from --to`).
     *
     * @return array{hour: int, day: int, month: int}
     */
    public function rebuildRange(CarbonInterface $from, CarbonInterface $to, ?CarbonInterface $now = null): array
    {
        $now ??= Date::now();
        $rebuilt = [];

        foreach (ApiStatsPeriod::cases() as $period) {
            $lastComplete = $this->previous($period, $period->floor($now));
            $until = $period->floor($to)->lessThan($lastComplete) ? $period->floor($to) : $lastComplete;

            $rebuilt[$period->value] = $this->rebuild($period, $period->floor($from), $until, PHP_INT_MAX);
        }

        return $rebuilt;
    }

    /** Start of the newest stored bucket for a period, or null if none exists yet. */
    public function lastBucket(ApiStatsPeriod $period): ?CarbonInterface
    {
        $bucket = ApiRequestStat::query()->forPeriod($period)->max('bucket');

        return $bucket === null ? null : Date::parse($bucket);
    }

    /**
     * Walk the buckets in [$from, $until] that have source rows, rebuilding each.
     */
    private function rebuild(ApiStatsPeriod $period, CarbonInterface $from, CarbonInterface $until, int $limit): int
    {
        $cursor = $period->floor($from);
        $end = $period->next($until);
        $rebuilt = 0;

        while ($rebuilt < $limit && $cursor->lessThan($end)) {
            $next = $this->nextSourceTimestamp($period, $cursor, $end);

            if ($next === null) {
                break;
            }

            $bucket = $period->floor($next);
            $this->rebuildBucket($period, $bucket);
            $cursor = $period->next($bucket);
            $rebuilt++;
        }

        return $rebuilt;
    }

    /**
     * Where a scheduled run starts: the trailing window, or further back when
     * stats are behind. With no stats yet, from the oldest source row.
     */
    private function scheduledStart(ApiStatsPeriod $period, CarbonInterface $lastComplete): ?CarbonInterface
    {
        $trailingStart = $lastComplete;

        for ($i = 1; $i < self::TRAILING_BUCKETS; $i++) {
            $trailingStart = $this->previous($period, $trailingStart);
        }

        $lastBucket = $this->lastBucket($period);

        if ($lastBucket !== null) {
            return $lastBucket->lessThan($trailingStart) ? $lastBucket : $trailingStart;
        }

        $oldest = $this->oldestSourceTimestamp($period);

        return $oldest === null ? null : $period->floor($oldest);
    }

    private function nextSourceTimestamp(ApiStatsPeriod $period, CarbonInterface $from, CarbonInterface $until): ?CarbonInterface
    {
        $source = $period->source();

        $value = $source === null
            ? DB::table('api_request_logs')
                ->where('created_at', '>=', $from->format(self::BUCKET_FORMAT))
                ->where('created_at', '<', $until->format(self::BUCKET_FORMAT))
                ->min('created_at')
            : DB::table('api_request_stats')
                ->where('period', $source->value)
                ->where('bucket', '>=', $from->format(self::BUCKET_FORMAT))
                ->where('bucket', '<', $until->format(self::BUCKET_FORMAT))
                ->min('bucket');

        return $value === null ? null : Date::parse($value);
    }

    private function oldestSourceTimestamp(ApiStatsPeriod $period): ?CarbonInterface
    {
        $source = $period->source();

        $value = $source === null
            ? DB::table('api_request_logs')->min('created_at')
            : DB::table('api_request_stats')->where('period', $source->value)->min('bucket');

        return $value === null ? null : Date::parse($value);
    }

    private function rebuildBucket(ApiStatsPeriod $period, CarbonInterface $bucket): void
    {
        $start = $bucket->format(self::BUCKET_FORMAT);
        $end = $period->next($bucket)->format(self::BUCKET_FORMAT);

        $rows = $period->source() === null
            ? $this->aggregateRawLogs($start, $end)
            : $this->aggregateStats($period->source(), $start, $end);

        $rows = array_map(fn (object $row): array => [
            ...$this->normalize((array) $row),
            'period' => $period->value,
            'bucket' => $start,
        ], $rows);

        DB::transaction(function () use ($period, $start, $rows): void {
            DB::table('api_request_stats')
                ->where('period', $period->value)
                ->where('bucket', $start)
                ->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('api_request_stats')->insert($chunk);
            }
        });
    }

    /**
     * Sample-weighted: every count is SUM(sample_weight) rather than COUNT(*),
     * so a 2xx row kept at a 10% sample rate stands in for ten requests.
     *
     * @return list<object>
     */
    private function aggregateRawLogs(string $start, string $end): array
    {
        return DB::table('api_request_logs')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->groupBy('method', 'route_uri', 'status_code', 'status_class')
            ->select([
                'method',
                'route_uri',
                'status_code',
                'status_class',
                DB::raw('MAX(api_version) as api_version'),
                DB::raw('SUM(sample_weight) as requests'),
                DB::raw('SUM(duration_ms * sample_weight) as duration_sum_ms'),
                DB::raw('MIN(duration_ms) as duration_min_ms'),
                DB::raw('MAX(duration_ms) as duration_max_ms'),
                DB::raw('SUM(db_time_ms * sample_weight) as db_time_sum_ms'),
                DB::raw('SUM(request_size * sample_weight) as request_bytes'),
                DB::raw('SUM(COALESCE(response_size, 0) * sample_weight) as response_bytes'),
                ...ApiRequestStat::rawHistogramSelects(),
            ])
            ->get()
            ->all();
    }

    /**
     * @return list<object>
     */
    private function aggregateStats(ApiStatsPeriod $source, string $start, string $end): array
    {
        return DB::table('api_request_stats')
            ->where('period', $source->value)
            ->where('bucket', '>=', $start)
            ->where('bucket', '<', $end)
            ->groupBy('method', 'route_uri', 'status_code', 'status_class')
            ->select([
                'method',
                'route_uri',
                'status_code',
                'status_class',
                DB::raw('MAX(api_version) as api_version'),
                DB::raw('SUM(requests) as requests'),
                DB::raw('SUM(duration_sum_ms) as duration_sum_ms'),
                DB::raw('MIN(duration_min_ms) as duration_min_ms'),
                DB::raw('MAX(duration_max_ms) as duration_max_ms'),
                DB::raw('SUM(db_time_sum_ms) as db_time_sum_ms'),
                DB::raw('SUM(request_bytes) as request_bytes'),
                DB::raw('SUM(response_bytes) as response_bytes'),
                ...array_map(fn (string $column) => DB::raw("SUM({$column}) as {$column}"), ApiRequestStat::histogramColumns()),
            ])
            ->get()
            ->all();
    }

    /**
     * Drivers return aggregates as strings/floats inconsistently — cast to the
     * column types so inserts behave identically on MySQL and SQLite.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        foreach (['requests', 'request_bytes', 'response_bytes', 'status_code', 'status_class', ...ApiRequestStat::histogramColumns()] as $column) {
            $row[$column] = (int) round((float) ($row[$column] ?? 0));
        }

        foreach (['duration_sum_ms', 'duration_min_ms', 'duration_max_ms', 'db_time_sum_ms'] as $column) {
            $row[$column] = round((float) ($row[$column] ?? 0), 2);
        }

        return $row;
    }

    private function previous(ApiStatsPeriod $period, CarbonInterface $bucket): CarbonInterface
    {
        return match ($period) {
            ApiStatsPeriod::Hour => $bucket->subHour(),
            ApiStatsPeriod::Day => $bucket->subDay(),
            ApiStatsPeriod::Month => $bucket->subMonthNoOverflow(),
        };
    }
}
