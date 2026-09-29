<?php

namespace App\Jobs\Report;

use App\Services\Report\ReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin scheduled trigger: queues a report for every schedule that has come
 * due. Schedules run on the hour, so an hourly sweep never misses one; each
 * run advances its schedule's next_run_at, so a rerun is a no-op.
 */
class RunScheduledReports implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function handle(ReportService $reports): void
    {
        $reports->runDueSchedules();
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: RunScheduledReports', [
            'job' => self::class,
            'exception' => $exception,
        ]);
    }
}
