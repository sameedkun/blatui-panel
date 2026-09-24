<?php

namespace App\Services\ApiLog;

use App\Enum\ApiStatsPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Enforces config('api_logs.retention'): raw logs + payloads 7 days,
 * exceptions 30, hour stats 90, day stats 365, month stats forever.
 *
 * A tier's source is never pruned past what the next tier has already rolled
 * up — raw logs never past the last aggregated hour, hour rows never past the
 * last rolled-up day, day rows never past the last rolled-up month — so a
 * stalled aggregator delays pruning instead of losing data. Cutoffs are
 * aligned to the next tier's bucket boundary, so no bucket is ever left with
 * half its source rows (which a re-aggregation would then under-count).
 */
class RetentionService
{
    private const int CHUNK_SIZE = 5000;

    public function __construct(private readonly AggregationService $aggregation) {}

    /**
     * @return array{raw: int, payloads: int, exceptions: int, hour: int, day: int}
     */
    public function prune(?CarbonInterface $now = null): array
    {
        $now ??= Date::now();

        $rawCutoff = $this->guardedCutoff(
            $now->subDays((int) config('api_logs.retention.raw_days', 7))->startOfHour(),
            ApiStatsPeriod::Hour,
            'raw API request logs',
        );

        $hourCutoff = $this->guardedCutoff(
            $now->subDays((int) config('api_logs.retention.hourly_days', 90))->startOfDay(),
            ApiStatsPeriod::Day,
            'hourly API stats',
        );

        $dayCutoff = $this->guardedCutoff(
            $now->subDays((int) config('api_logs.retention.daily_days', 365))->startOfMonth(),
            ApiStatsPeriod::Month,
            'daily API stats',
        );

        $exceptionCutoff = $now->subDays((int) config('api_logs.retention.exception_days', 30));

        return [
            'raw' => $rawCutoff ? $this->deleteInChunks('api_request_logs', 'created_at', $rawCutoff) : 0,
            'payloads' => $rawCutoff ? $this->deleteInChunks('api_request_payloads', 'created_at', $rawCutoff) : 0,
            'exceptions' => $this->deleteInChunks('api_request_exceptions', 'created_at', $exceptionCutoff),
            'hour' => $hourCutoff ? $this->deleteInChunks('api_request_stats', 'bucket', $hourCutoff, ApiStatsPeriod::Hour) : 0,
            'day' => $dayCutoff ? $this->deleteInChunks('api_request_stats', 'bucket', $dayCutoff, ApiStatsPeriod::Day) : 0,
        ];
    }

    /**
     * The retention cutoff, pulled back to the end of the newest bucket the
     * next tier has built. Null (skip pruning this tier) when that tier has
     * nothing yet — aggregation has never run, so nothing is safe to delete.
     */
    private function guardedCutoff(CarbonInterface $cutoff, ApiStatsPeriod $rolledInto, string $label): ?CarbonInterface
    {
        $lastBucket = $this->aggregation->lastBucket($rolledInto);

        if ($lastBucket === null) {
            if ($this->hasRowsBefore($rolledInto, $cutoff)) {
                Log::warning("Skipped pruning {$label}: nothing has been aggregated into {$rolledInto->value} stats yet.");
            }

            return null;
        }

        $aggregatedUntil = $rolledInto->next($lastBucket);

        return $aggregatedUntil->lessThan($cutoff) ? $aggregatedUntil : $cutoff;
    }

    private function hasRowsBefore(ApiStatsPeriod $rolledInto, CarbonInterface $cutoff): bool
    {
        $source = $rolledInto->source();

        return $source === null
            ? DB::table('api_request_logs')->where('created_at', '<', $cutoff->format('Y-m-d H:i:s'))->exists()
            : DB::table('api_request_stats')->where('period', $source->value)->where('bucket', '<', $cutoff->format('Y-m-d H:i:s'))->exists();
    }

    /** Chunked so a large backlog never holds a long table lock. */
    private function deleteInChunks(string $table, string $column, CarbonInterface $cutoff, ?ApiStatsPeriod $period = null): int
    {
        $total = 0;

        do {
            $deleted = DB::table($table)
                ->when($period, fn ($query) => $query->where('period', $period->value))
                ->where($column, '<', $cutoff->format('Y-m-d H:i:s'))
                ->limit(self::CHUNK_SIZE)
                ->delete();

            $total += $deleted;
        } while ($deleted === self::CHUNK_SIZE);

        return $total;
    }
}
