<?php

namespace App\Models\ApiLog;

use App\Enum\ApiStatsPeriod;
use App\Services\ApiLog\AggregationService;
use Database\Factories\ApiLog\ApiRequestStatFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * One (period, bucket, method, route_uri, status_code) rollup row. Built and
 * replaced wholesale by {@see AggregationService} — never
 * edited in place. `requests` is a sample-weighted count, so it stays an
 * accurate estimate when raw-log sampling is on.
 *
 * The h_* columns are a latency histogram: each counts requests whose
 * duration fell in (previous bound, this bound] ms, h_inf everything above
 * the last bound. Plain additive counts, so rolling hours into days into
 * months is a straight SUM(), and percentiles are read off the summed buckets.
 */
class ApiRequestStat extends Model
{
    /** @use HasFactory<ApiRequestStatFactory> */
    use HasFactory;

    /** Upper bounds (ms) of the histogram buckets, mirrored by the h_le_* columns. */
    public const array HISTOGRAM_BOUNDS = [10, 25, 50, 100, 250, 500, 1000, 2500, 5000, 10000];

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period' => ApiStatsPeriod::class,
            'bucket' => 'datetime',
            'status_code' => 'integer',
            'status_class' => 'integer',
            'requests' => 'integer',
            'duration_sum_ms' => 'float',
            'duration_min_ms' => 'float',
            'duration_max_ms' => 'float',
            'db_time_sum_ms' => 'float',
        ];
    }

    /**
     * The histogram column names, in bound order, ending with h_inf.
     *
     * @return list<string>
     */
    public static function histogramColumns(): array
    {
        return [
            ...array_map(fn (int $bound): string => "h_le_{$bound}", self::HISTOGRAM_BOUNDS),
            'h_inf',
        ];
    }

    /**
     * SELECT expressions building the histogram from raw api_request_logs rows
     * (sample-weighted), aliased to the h_* column names.
     *
     * @return list<Expression>
     */
    public static function rawHistogramSelects(): array
    {
        $selects = [];
        $lower = null;

        foreach (self::HISTOGRAM_BOUNDS as $bound) {
            $condition = $lower === null ? "duration_ms <= {$bound}" : "duration_ms > {$lower} AND duration_ms <= {$bound}";
            $selects[] = DB::raw("SUM(CASE WHEN {$condition} THEN sample_weight ELSE 0 END) as h_le_{$bound}");
            $lower = $bound;
        }

        $selects[] = DB::raw("SUM(CASE WHEN duration_ms > {$lower} THEN sample_weight ELSE 0 END) as h_inf");

        return $selects;
    }

    /** The histogram column a request of $durationMs is counted in. */
    public static function histogramColumnFor(float $durationMs): string
    {
        foreach (self::HISTOGRAM_BOUNDS as $bound) {
            if ($durationMs <= $bound) {
                return "h_le_{$bound}";
            }
        }

        return 'h_inf';
    }

    /**
     * Approximate a latency percentile (0-100) from summed histogram counts,
     * keyed by column name. Returns the upper bound of the bucket the
     * percentile falls in (the last bound for h_inf), or null with no data.
     *
     * @param  array<string, int|float|string|null>  $histogram
     */
    public static function percentileFromHistogram(array $histogram, float $percentile): ?float
    {
        // Only the h_* keys count — callers may pass a whole merged stats row.
        $histogram = array_intersect_key($histogram, array_flip(self::histogramColumns()));
        $total = array_sum(array_map(fn ($count): float => (float) $count, $histogram));

        if ($total <= 0) {
            return null;
        }

        $target = $total * ($percentile / 100);
        $running = 0.0;

        foreach (self::HISTOGRAM_BOUNDS as $bound) {
            $running += (float) ($histogram["h_le_{$bound}"] ?? 0);

            if ($running >= $target) {
                return (float) $bound;
            }
        }

        return (float) self::HISTOGRAM_BOUNDS[array_key_last(self::HISTOGRAM_BOUNDS)];
    }

    public function scopeForPeriod(Builder $query, ApiStatsPeriod $period): Builder
    {
        return $query->where('period', $period->value);
    }
}
