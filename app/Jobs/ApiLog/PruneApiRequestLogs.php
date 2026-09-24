<?php

namespace App\Jobs\ApiLog;

use App\Services\ApiLog\RetentionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daily sweep enforcing config('api_logs.retention') across the raw logs,
 * payloads, exceptions and hour/day stats — see {@see RetentionService}.
 */
class PruneApiRequestLogs implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function handle(RetentionService $retention): void
    {
        $retention->prune();
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: PruneApiRequestLogs', [
            'job' => self::class,
            'exception' => $exception,
        ]);
    }
}
