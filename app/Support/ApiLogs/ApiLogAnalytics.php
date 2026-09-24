<?php

declare(strict_types=1);

namespace App\Support\ApiLogs;

use App\Enum\ApiStatsPeriod;
use App\Models\ApiLog\ApiRequestStat;
use App\Services\ApiLog\AggregationService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Every number on the API analytics page (and the Requests page's stat cards).
 *
 * A range is answered by its own rollup tier for the buckets that tier has
 * already built, then by progressively finer tiers for the tail it hasn't
 * (today's hours, then the current hour's raw logs) — so a 30-day chart is
 * complete up to this minute, not up to the last aggregation run. Raw and
 * rollup rows share one measure shape (sample-weighted counts, duration sums,
 * the latency histogram), so every metric is computed the same way whichever
 * tier supplied it.
 *
 * Metrics that only exist on raw rows — error codes, IPs, users, client types
 * — return null once a range reaches past raw retention.
 */
class ApiLogAnalytics
{
    /** @var array<string, CarbonInterface|false> */
    private array $lastBuckets = [];

    public function __construct(private readonly AggregationService $aggregation) {}

    public function range(?string $key): AnalyticsRange
    {
        $oldestMonth = ApiRequestStat::query()->forPeriod(ApiStatsPeriod::Month)->min('bucket');

        return AnalyticsRange::fromKey($key, allTimeStart: $oldestMonth ? Date::parse($oldestMonth) : null);
    }

    /**
     * @return array{requests: int, per_minute: float, errors_4xx: int, errors_5xx: int, rate_4xx: float, rate_5xx: float, avg_ms: float|null, p50_ms: float|null, p95_ms: float|null, p99_ms: float|null, max_ms: float|null, avg_response_bytes: float|null, unique_users: int|null}
     */
    public function kpis(AnalyticsRange $range): array
    {
        return $this->remember($range, 'kpis', function () use ($range): array {
            $rows = $this->aggregate($range, ['status_class']);
            $total = $this->merge($rows->all());
            $requests = (int) $total['requests'];
            $byClass = $rows->pluck('requests', 'status_class');

            return [
                'requests' => $requests,
                'per_minute' => round($requests / $range->minutes(), 2),
                'errors_4xx' => (int) ($byClass[4] ?? 0),
                'errors_5xx' => (int) ($byClass[5] ?? 0),
                'rate_4xx' => $this->percent((int) ($byClass[4] ?? 0), $requests),
                'rate_5xx' => $this->percent((int) ($byClass[5] ?? 0), $requests),
                'avg_ms' => $requests > 0 ? round($total['duration_sum_ms'] / $requests, 1) : null,
                'p50_ms' => ApiRequestStat::percentileFromHistogram($total, 50),
                'p95_ms' => ApiRequestStat::percentileFromHistogram($total, 95),
                'p99_ms' => ApiRequestStat::percentileFromHistogram($total, 99),
                'max_ms' => $requests > 0 ? $total['duration_max_ms'] : null,
                'avg_response_bytes' => $requests > 0 ? round($total['response_bytes'] / $requests) : null,
                'unique_users' => $range->withinRawRetention()
                    ? (int) $this->raw($range)->whereNotNull('user_id')->distinct()->count('user_id')
                    : null,
            ];
        });
    }

    /**
     * Requests per chart bucket, split by status class.
     *
     * @return array{labels: list<string>, series: array<int, list<int>>}
     */
    public function requestsOverTime(AnalyticsRange $range): array
    {
        return $this->remember($range, 'requests_over_time', function () use ($range): array {
            $buckets = $range->buckets();
            $series = array_fill_keys([2, 3, 4, 5], array_fill_keys(array_keys($buckets), 0));

            foreach ($this->aggregate($range, ['status_class'], byTime: true) as $row) {
                if (isset($series[$row['status_class']][$row['bucket']])) {
                    $series[$row['status_class']][$row['bucket']] = (int) $row['requests'];
                }
            }

            return [
                'labels' => array_values($buckets),
                'series' => array_map(fn (array $values): array => array_values($values), $series),
            ];
        });
    }

    /**
     * Average, p50 and p95 latency per chart bucket (null where a bucket had no traffic).
     *
     * @return array{labels: list<string>, avg: list<float|null>, p50: list<float|null>, p95: list<float|null>}
     */
    public function latencyOverTime(AnalyticsRange $range): array
    {
        return $this->remember($range, 'latency_over_time', function () use ($range): array {
            $buckets = $range->buckets();
            $rows = $this->aggregate($range, [], byTime: true)->keyBy('bucket');
            $avg = $p50 = $p95 = [];

            foreach (array_keys($buckets) as $bucket) {
                $row = $rows[$bucket] ?? null;
                $requests = (int) ($row['requests'] ?? 0);

                $avg[] = $requests > 0 ? round($row['duration_sum_ms'] / $requests, 1) : null;
                $p50[] = $requests > 0 ? ApiRequestStat::percentileFromHistogram($row, 50) : null;
                $p95[] = $requests > 0 ? ApiRequestStat::percentileFromHistogram($row, 95) : null;
            }

            return ['labels' => array_values($buckets), 'avg' => $avg, 'p50' => $p50, 'p95' => $p95];
        });
    }

    /** @return array<int, int> status code => requests, busiest first */
    public function statusCodes(AnalyticsRange $range): array
    {
        return $this->remember($range, 'status_codes', fn (): array => $this->aggregate($range, ['status_code'])
            ->sortByDesc('requests')
            ->mapWithKeys(fn (array $row): array => [(int) $row['status_code'] => (int) $row['requests']])
            ->all());
    }

    /** @return array<string, int> method => requests, busiest first */
    public function methods(AnalyticsRange $range): array
    {
        return $this->remember($range, 'methods', fn (): array => $this->aggregate($range, ['method'])
            ->sortByDesc('requests')
            ->mapWithKeys(fn (array $row): array => [$row['method'] => (int) $row['requests']])
            ->all());
    }

    /** @return array<string, int> API version => requests ("—" for requests that matched no version) */
    public function versions(AnalyticsRange $range): array
    {
        return $this->remember($range, 'versions', fn (): array => $this->aggregate($range, ['api_version'])
            ->sortByDesc('requests')
            ->mapWithKeys(fn (array $row): array => [($row['api_version'] ?? '') ?: '—' => (int) $row['requests']])
            ->all());
    }

    /**
     * One row per endpoint (method + route template).
     *
     * @return list<array{method: string, route_uri: string, requests: int, errors_4xx: int, errors_5xx: int, error_rate: float, avg_ms: float|null, p95_ms: float|null, max_ms: float|null}>
     */
    public function endpoints(AnalyticsRange $range): array
    {
        return $this->remember($range, 'endpoints', function () use ($range): array {
            return $this->aggregate($range, ['method', 'route_uri', 'status_class'])
                ->groupBy(fn (array $row): string => $row['method'].' '.$row['route_uri'])
                ->map(function (Collection $rows): array {
                    $total = $this->merge($rows->all());
                    $requests = (int) $total['requests'];
                    $byClass = $rows->pluck('requests', 'status_class');
                    $errors4xx = (int) ($byClass[4] ?? 0);
                    $errors5xx = (int) ($byClass[5] ?? 0);

                    return [
                        'method' => $rows->first()['method'],
                        'route_uri' => $rows->first()['route_uri'],
                        'requests' => $requests,
                        'errors_4xx' => $errors4xx,
                        'errors_5xx' => $errors5xx,
                        'error_rate' => $this->percent($errors4xx + $errors5xx, $requests),
                        'avg_ms' => $requests > 0 ? round($total['duration_sum_ms'] / $requests, 1) : null,
                        'p95_ms' => ApiRequestStat::percentileFromHistogram($total, 95),
                        'max_ms' => $requests > 0 ? $total['duration_max_ms'] : null,
                    ];
                })
                ->values()
                ->all();
        });
    }

    /** @return list<array<string, mixed>> the slowest endpoints by p95 (then average), slowest first */
    public function slowestEndpoints(AnalyticsRange $range, int $limit = 10): array
    {
        return collect($this->endpoints($range))
            ->sortBy([['p95_ms', 'desc'], ['avg_ms', 'desc']])
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> endpoints with any 4xx/5xx, most server errors first */
    public function erroringEndpoints(AnalyticsRange $range, int $limit = 10): array
    {
        return collect($this->endpoints($range))
            ->filter(fn (array $row): bool => $row['errors_4xx'] + $row['errors_5xx'] > 0)
            ->sortBy([['errors_5xx', 'desc'], ['errors_4xx', 'desc']])
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function busiestEndpoints(AnalyticsRange $range, int $limit = 10): array
    {
        return collect($this->endpoints($range))->sortByDesc('requests')->take($limit)->values()->all();
    }

    /** @return array<string, int>|null machine-readable error code => requests; null past raw retention */
    public function errorCodes(AnalyticsRange $range, int $limit = 10): ?array
    {
        return $this->rawBreakdown($range, 'error_codes', 'error_code', $limit);
    }

    /** @return array<string, int>|null */
    public function clientTypes(AnalyticsRange $range): ?array
    {
        return $this->rawBreakdown($range, 'client_types', 'client_type', 5);
    }

    /** @return array<string, int>|null */
    public function topIps(AnalyticsRange $range, int $limit = 10): ?array
    {
        return $this->rawBreakdown($range, 'top_ips', 'ip', $limit);
    }

    /**
     * @return list<array{user_id: int, name: string|null, email: string|null, type: string|null, requests: int}>|null
     */
    public function topUsers(AnalyticsRange $range, int $limit = 10): ?array
    {
        if (! $range->withinRawRetention()) {
            return null;
        }

        return $this->remember($range, 'top_users', fn (): array => $this->raw($range)
            ->whereNotNull('api_request_logs.user_id')
            ->leftJoin('users', 'users.id', '=', 'api_request_logs.user_id')
            ->groupBy('api_request_logs.user_id', 'users.name', 'users.email', 'users.type')
            ->orderByDesc('requests')
            ->limit($limit)
            ->get([
                'api_request_logs.user_id',
                'users.name',
                'users.email',
                'users.type',
                DB::raw('SUM(sample_weight) as requests'),
            ])
            ->map(fn (object $row): array => [
                'user_id' => (int) $row->user_id,
                'name' => $row->name,
                'email' => $row->email,
                'type' => $row->type,
                'requests' => (int) $row->requests,
            ])
            ->all());
    }

    /**
     * Exceptions grouped by fingerprint (their own 30-day retention caps how far back this reaches).
     *
     * @return list<array{fingerprint: string, class: string, message: string, file: string|null, line: int|null, occurrences: int, last_seen: string, sample_request_id: string}>
     */
    public function topExceptions(AnalyticsRange $range, int $limit = 10): array
    {
        return $this->remember($range, 'top_exceptions', fn (): array => DB::table('api_request_exceptions')
            ->where('created_at', '>=', $range->start->format('Y-m-d H:i:s'))
            ->groupBy('fingerprint', 'class', 'file', 'line')
            ->orderByDesc('occurrences')
            ->limit($limit)
            ->get([
                'fingerprint',
                'class',
                'file',
                'line',
                DB::raw('MAX(message) as message'),
                DB::raw('COUNT(*) as occurrences'),
                DB::raw('MAX(created_at) as last_seen'),
                DB::raw('MAX(request_id) as sample_request_id'),
            ])
            ->map(fn (object $row): array => [
                'fingerprint' => $row->fingerprint,
                'class' => $row->class,
                'message' => $row->message,
                'file' => $row->file,
                'line' => $row->line === null ? null : (int) $row->line,
                'occurrences' => (int) $row->occurrences,
                'last_seen' => (string) $row->last_seen,
                'sample_request_id' => $row->sample_request_id,
            ])
            ->all());
    }

    /**
     * @return array<string, int>|null
     */
    private function rawBreakdown(AnalyticsRange $range, string $metric, string $column, int $limit): ?array
    {
        if (! $range->withinRawRetention()) {
            return null;
        }

        return $this->remember($range, $metric, fn (): array => $this->raw($range)
            ->whereNotNull($column)
            ->groupBy($column)
            ->orderByDesc('requests')
            ->limit($limit)
            ->get([$column, DB::raw('SUM(sample_weight) as requests')])
            ->mapWithKeys(fn (object $row): array => [(string) $row->{$column} => (int) $row->requests])
            ->all());
    }

    /**
     * Rows grouped by $dimensions (and, with $byTime, by chart bucket), merged
     * across every tier that answers the range.
     *
     * @param  list<string>  $dimensions
     * @return Collection<int, array<string, mixed>>
     */
    private function aggregate(AnalyticsRange $range, array $dimensions, bool $byTime = false): Collection
    {
        $rows = collect();

        foreach ($this->segments($range->source(), $range->start, $range->end) as [$period, $from, $to]) {
            $rows = $rows->concat($this->segmentRows($range, $period, $from, $to, $dimensions, $byTime));
        }

        return $rows
            ->groupBy(fn (array $row): string => implode('|', array_map(
                fn (string $key): string => (string) ($row[$key] ?? ''),
                [...$dimensions, ...($byTime ? ['bucket'] : [])],
            )))
            ->map(fn (Collection $group): array => [
                ...array_intersect_key($group->first(), array_flip([...$dimensions, 'bucket'])),
                ...$this->merge($group->all()),
            ])
            ->values();
    }

    /**
     * Which tier covers which part of [$from, $to): $period for the buckets it
     * has already built, then the next finer tier for the rest, down to raw.
     *
     * @return list<array{0: ApiStatsPeriod|null, 1: CarbonInterface, 2: CarbonInterface}>
     */
    private function segments(?ApiStatsPeriod $period, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($period === null) {
            return [[null, $from, $to]];
        }

        $lastBucket = $this->lastBucket($period);
        $coveredUntil = $lastBucket ? $period->next($lastBucket) : null;

        if ($coveredUntil === null || $coveredUntil->lessThanOrEqualTo($from)) {
            return $this->segments($period->source(), $from, $to);
        }

        $cut = $coveredUntil->lessThan($to) ? $coveredUntil : $to;
        $segments = [[$period, $period->floor($from), $cut]];

        return $cut->lessThan($to)
            ? [...$segments, ...$this->segments($period->source(), $cut, $to)]
            : $segments;
    }

    /**
     * @param  list<string>  $dimensions
     * @return list<array<string, mixed>>
     */
    private function segmentRows(AnalyticsRange $range, ?ApiStatsPeriod $period, CarbonInterface $from, CarbonInterface $to, array $dimensions, bool $byTime): array
    {
        $isRaw = $period === null;
        $timeColumn = $isRaw ? 'created_at' : 'bucket';

        $query = DB::table($isRaw ? 'api_request_logs' : 'api_request_stats')
            ->when(! $isRaw, fn (Builder $q) => $q->where('period', $period->value))
            ->where($timeColumn, '>=', $from->format('Y-m-d H:i:s'))
            ->where($timeColumn, '<', $to->format('Y-m-d H:i:s'));

        $groups = $dimensions;

        if ($byTime) {
            // Raw rows are grouped per minute in SQL, then folded into chart buckets below.
            $query->addSelect(DB::raw(($isRaw ? $this->minuteExpression('created_at') : 'bucket').' as time_bucket'));
            $groups[] = 'time_bucket';
        }

        $rows = $query
            ->addSelect([...$dimensions, ...$this->measures($isRaw)])
            ->when($groups !== [], fn (Builder $q) => $q->groupBy($groups))
            ->get();

        return $rows->map(function (object $row) use ($range, $byTime): array {
            $row = (array) $row;

            if ($byTime) {
                $row['bucket'] = $range->bucketKey(Date::parse($row['time_bucket']));
                unset($row['time_bucket']);
            }

            return $row;
        })->all();
    }

    /**
     * The shared measure shape, from raw rows (sample-weighted) or rollups.
     *
     * @return list<Expression>
     */
    private function measures(bool $isRaw): array
    {
        if ($isRaw) {
            return [
                DB::raw('SUM(sample_weight) as requests'),
                DB::raw('SUM(duration_ms * sample_weight) as duration_sum_ms'),
                DB::raw('MAX(duration_ms) as duration_max_ms'),
                DB::raw('SUM(COALESCE(response_size, 0) * sample_weight) as response_bytes'),
                ...ApiRequestStat::rawHistogramSelects(),
            ];
        }

        return [
            DB::raw('SUM(requests) as requests'),
            DB::raw('SUM(duration_sum_ms) as duration_sum_ms'),
            DB::raw('MAX(duration_max_ms) as duration_max_ms'),
            DB::raw('SUM(response_bytes) as response_bytes'),
            ...array_map(fn (string $column) => DB::raw("SUM({$column}) as {$column}"), ApiRequestStat::histogramColumns()),
        ];
    }

    /**
     * Sum the additive measures of several rows (max for duration_max_ms).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, float|int>
     */
    private function merge(array $rows): array
    {
        $merged = ['requests' => 0, 'duration_sum_ms' => 0.0, 'duration_max_ms' => 0.0, 'response_bytes' => 0];

        foreach (ApiRequestStat::histogramColumns() as $column) {
            $merged[$column] = 0;
        }

        foreach ($rows as $row) {
            foreach ($merged as $key => $value) {
                $merged[$key] = $key === 'duration_max_ms'
                    ? max($value, (float) ($row[$key] ?? 0))
                    : $value + (float) ($row[$key] ?? 0);
            }
        }

        return $merged;
    }

    private function raw(AnalyticsRange $range): Builder
    {
        return DB::table('api_request_logs')
            ->where('api_request_logs.created_at', '>=', $range->start->format('Y-m-d H:i:s'))
            ->where('api_request_logs.created_at', '<', $range->end->format('Y-m-d H:i:s.v'));
    }

    /** A per-minute bucket of a datetime column — the one driver-specific bit of SQL here. */
    private function minuteExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d %H:%M:00', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM-DD HH24:MI:00')",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d %H:%i:00')",
        };
    }

    private function lastBucket(ApiStatsPeriod $period): ?CarbonInterface
    {
        $this->lastBuckets[$period->value] ??= $this->aggregation->lastBucket($period) ?? false;

        return $this->lastBuckets[$period->value] ?: null;
    }

    private function percent(int $part, int $total): float
    {
        return $total > 0 ? round($part / $total * 100, 2) : 0.0;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function remember(AnalyticsRange $range, string $metric, callable $callback): mixed
    {
        return Cache::remember("api_logs:analytics:{$range->key}:{$metric}", $range->cacheSeconds(), $callback);
    }
}
