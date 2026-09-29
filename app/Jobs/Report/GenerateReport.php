<?php

namespace App\Jobs\Report;

use App\Models\Report\GeneratedReport;
use App\Services\Report\ReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Builds one requested report in the background. All logic lives in
 * {@see ReportService::generate()}; this job only restores the requester's
 * locale (headings and labels are translated) and invokes it.
 */
class GenerateReport implements ShouldQueue
{
    use Queueable;

    /** A failed report is retried by the admin from the Reports page, not automatically. */
    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public int $reportId,
        public ?string $locale = null,
    ) {}

    public function handle(ReportService $reports): void
    {
        App::setLocale($this->locale ?? config('app.locale'));

        $report = GeneratedReport::query()->find($this->reportId);

        // Deleted while it waited in the queue — nothing to build.
        if ($report === null) {
            return;
        }

        $reports->generate($report);
    }

    /**
     * A timeout or worker crash never reaches generate()'s own error handling,
     * so the row would sit "processing" forever — mark it failed here instead.
     */
    public function failed(?Throwable $exception): void
    {
        $report = GeneratedReport::query()->find($this->reportId);

        if ($report !== null && $report->status->isInFlight()) {
            app(ReportService::class)->fail($report, $exception ?? new RuntimeException('Report generation did not finish.'));
        }

        Log::channel('jobs')->error('Job failed: GenerateReport', [
            'job' => self::class,
            'report_id' => $this->reportId,
            'exception' => $exception,
        ]);
    }
}
