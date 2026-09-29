<?php

namespace App\Jobs\Report;

use App\Services\Report\ReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deletes generated reports (file + row) past their retention date
 * (`dashboard.reports.retention_days`). Idempotent — the next run catches
 * anything a failed run left behind.
 */
class PruneGeneratedReports implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function handle(ReportService $reports): void
    {
        $reports->pruneExpired();
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: PruneGeneratedReports', [
            'job' => self::class,
            'exception' => $exception,
        ]);
    }
}
