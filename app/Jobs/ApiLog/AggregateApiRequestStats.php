<?php

namespace App\Jobs\ApiLog;

use App\Services\ApiLog\AggregationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hourly (at :05, once the previous hour's buffered logs have been flushed):
 * rolls raw API logs into hour stats, hours into days, days into months.
 * Idempotent and self-catching-up — see {@see AggregationService}.
 */
class AggregateApiRequestStats implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function handle(AggregationService $aggregation): void
    {
        $aggregation->aggregate();
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: AggregateApiRequestStats', [
            'job' => self::class,
            'exception' => $exception,
        ]);
    }
}
