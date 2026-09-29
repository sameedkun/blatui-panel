<?php

namespace App\Services\Report;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityModule;
use App\Enum\ReportFormat;
use App\Enum\ReportFrequency;
use App\Enum\ReportSource;
use App\Enum\ReportStatus;
use App\Jobs\Report\GenerateReport;
use App\Mail\Report\ReportReadyMail;
use App\Models\Report\GeneratedReport;
use App\Models\Report\ScheduledReport;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Dashboard\DashboardRegistry;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Reports\ReportDefinition;
use App\Support\Dashboard\Reports\ReportDocument;
use App\Support\Dashboard\Reports\Writers\ReportWriter;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The only place report state changes — requesting, generating, delivering,
 * retrying and deleting generated reports, and managing schedules. Mirrors
 * the app's other lifecycle services: callers (the Reports page, the queue
 * job, the scheduler) never touch GeneratedReport/ScheduledReport rows or
 * report files directly, and every mutation writes its own audit entry.
 *
 * Files go to the default filesystem disk via the plain Storage facade, so
 * they follow FILESYSTEM_DISK (local, R2, S3, …) like every other upload.
 */
class ReportService
{
    public function __construct(private readonly DashboardRegistry $registry) {}

    /**
     * Record a report request and queue its generation.
     *
     * @param  array<string, mixed>  $filters
     */
    public function request(
        ReportDefinition $definition,
        DateRange $range,
        array $filters,
        ReportFormat $format,
        ?User $requester = null,
        ?ScheduledReport $schedule = null,
    ): GeneratedReport {
        $report = GeneratedReport::create([
            'report' => $definition->key(),
            'title' => $schedule->name ?? $definition->label(),
            'format' => $format,
            'status' => ReportStatus::Pending,
            'source' => $schedule ? ReportSource::Scheduled : ReportSource::Manual,
            'range_start' => $range->start,
            'range_end' => $range->end,
            'filters' => $definition->normalizeFilters($filters) ?: null,
            'requested_by' => $requester?->id,
            'scheduled_report_id' => $schedule?->id,
        ]);

        GenerateReport::dispatch($report->id, app()->getLocale());

        if ($schedule === null) {
            ActivityLogger::log(ActivityModule::Report, ActivityAction::Created, $report, [
                'type' => 'report_generated',
                'report' => $definition->key(),
                'format' => $format->value,
                'period' => $range->label(),
            ], causer: $requester ?? false);
        }

        return $report;
    }

    /**
     * Build the file for a queued report and store it on the default disk.
     *
     * Never throws for a report-level failure: the row is marked Failed with
     * the reason, so the page shows it and the admin can retry. Completed
     * scheduled runs are then emailed to the schedule's recipients.
     */
    public function generate(GeneratedReport $report): void
    {
        if ($report->status === ReportStatus::Completed) {
            return;
        }

        $report->update(['status' => ReportStatus::Processing, 'started_at' => Date::now(), 'error' => null]);
        $temporary = tempnam(sys_get_temp_dir(), 'report-') ?: throw new RuntimeException('Unable to create a temporary file.');

        try {
            $definition = $this->registry->report($report->report)
                ?? throw new RuntimeException("Report definition [{$report->report}] is not registered.");

            $range = $report->range();
            $filters = $definition->normalizeFilters($report->filters ?? []);

            $rows = ReportWriter::for($report->format)->write(new ReportDocument(
                title: $report->title,
                columns: $definition->columns(),
                rows: $definition->rows($range, $filters),
                meta: [
                    __('dashboard.reports.meta.report') => $definition->label(),
                    __('dashboard.reports.meta.period') => $range->label(),
                    ...$definition->describeFilters($filters),
                    __('dashboard.reports.meta.generated_at') => Date::now()->translatedFormat('M j, Y H:i T'),
                ],
                summary: $definition->summary($range, $filters),
            ), $temporary);

            $path = trim((string) config('dashboard.reports.directory', 'reports'), '/')."/{$report->ulid}.{$report->format->extension()}";
            $stream = fopen($temporary, 'rb') ?: throw new RuntimeException('Unable to read the generated report.');

            try {
                Storage::put($path, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $report->update([
                'status' => ReportStatus::Completed,
                'file_path' => $path,
                'file_size' => filesize($temporary) ?: null,
                'row_count' => $rows,
                'completed_at' => Date::now(),
                'expires_at' => Date::now()->addDays((int) config('dashboard.reports.retention_days', 30)),
            ]);
        } catch (Throwable $exception) {
            $this->fail($report, $exception);

            return;
        } finally {
            @unlink($temporary);
        }

        if ($report->scheduled_report_id !== null) {
            $this->deliver($report);
        }
    }

    /** Mark a report failed and record why. */
    public function fail(GeneratedReport $report, Throwable $exception): void
    {
        $report->update([
            'status' => ReportStatus::Failed,
            'error' => Str::limit($exception->getMessage(), 1000),
            'completed_at' => Date::now(),
        ]);

        Log::channel('jobs')->error('Report generation failed', [
            'report_id' => $report->id,
            'report' => $report->report,
            'exception' => $exception,
        ]);
    }

    /**
     * Email a completed scheduled report to its schedule's recipients —
     * attached when it fits under the configured size, otherwise as a link to
     * the Reports page (which still requires signing in).
     */
    public function deliver(GeneratedReport $report): void
    {
        $recipients = array_values(array_filter((array) $report->schedule?->recipients));

        if (! $report->isDownloadable() || $recipients === []) {
            return;
        }

        $attach = $report->file_size !== null
            && $report->file_size <= (int) config('dashboard.reports.max_attachment_kb', 10240) * 1024;

        Mail::to($recipients)->send(new ReportReadyMail($report, $attach));

        ActivityLogger::log(ActivityModule::Report, ActivityAction::Sent, $report, [
            'type' => 'report_sent',
            'report' => $report->report,
            'recipients' => count($recipients),
            'attached' => $attach,
        ], causer: null, context: ActivityContext::Queue);
    }

    /** Put a failed report back on the queue. */
    public function retry(GeneratedReport $report): void
    {
        if ($report->status !== ReportStatus::Failed) {
            return;
        }

        $report->update(['status' => ReportStatus::Pending, 'error' => null, 'started_at' => null, 'completed_at' => null]);

        GenerateReport::dispatch($report->id, app()->getLocale());
    }

    /** Remove a report's file and its row. */
    public function delete(GeneratedReport $report, ?User $actor = null): void
    {
        $this->purge($report);

        ActivityLogger::log(ActivityModule::Report, ActivityAction::Deleted, null, [
            'type' => 'report_deleted',
            'report' => $report->report,
            'title' => $report->title,
            'period' => $report->range()->label(),
        ], causer: $actor ?? false);
    }

    /** Delete every report past its retention date. Returns how many were removed. */
    public function pruneExpired(?CarbonInterface $at = null): int
    {
        $removed = 0;

        GeneratedReport::query()
            ->where('expires_at', '<=', $at ?? Date::now())
            ->chunkById(200, function ($reports) use (&$removed): void {
                foreach ($reports as $report) {
                    $this->purge($report);
                    $removed++;
                }
            });

        return $removed;
    }

    /**
     * Create or update a schedule. Its next run is recomputed from the
     * (possibly changed) cadence every time it is saved.
     *
     * @param  array{name: string, report: string, format: ReportFormat, filters?: array<string, mixed>, frequency: ReportFrequency, day_of_week?: int|null, day_of_month?: int|null, hour: int, recipients: list<string>, is_active?: bool}  $attributes
     */
    public function saveSchedule(array $attributes, ?ScheduledReport $schedule = null, ?User $actor = null): ScheduledReport
    {
        $definition = $this->registry->report($attributes['report'])
            ?? throw new RuntimeException("Report definition [{$attributes['report']}] is not registered.");

        $schedule ??= new ScheduledReport(['created_by' => $actor?->id]);
        $isNew = ! $schedule->exists;

        $schedule->fill([
            ...$attributes,
            'filters' => $definition->normalizeFilters($attributes['filters'] ?? []) ?: null,
            'day_of_week' => $attributes['frequency'] === ReportFrequency::Weekly ? ($attributes['day_of_week'] ?? 1) : null,
            'day_of_month' => $attributes['frequency'] === ReportFrequency::Monthly ? ($attributes['day_of_month'] ?? 1) : null,
        ]);
        $schedule->fill(['next_run_at' => $schedule->nextRunAfter()])->save();

        ActivityLogger::log(ActivityModule::Report, $isNew ? ActivityAction::Created : ActivityAction::Updated, $schedule, [
            'type' => $isNew ? 'report_scheduled' : 'report_schedule_updated',
            'report' => $schedule->report,
            'frequency' => $schedule->frequency->value,
        ], causer: $actor ?? false);

        return $schedule;
    }

    /** Pause or resume a schedule. Resuming recomputes the next run from now. */
    public function toggleSchedule(ScheduledReport $schedule, ?User $actor = null): void
    {
        $schedule->is_active = ! $schedule->is_active;

        if ($schedule->is_active) {
            $schedule->fill(['next_run_at' => $schedule->nextRunAfter()]);
        }

        $schedule->save();

        ActivityLogger::log(ActivityModule::Report, ActivityAction::Updated, $schedule, [
            'type' => 'report_schedule_updated',
            'report' => $schedule->report,
            'is_active' => $schedule->is_active,
        ], causer: $actor ?? false);
    }

    public function deleteSchedule(ScheduledReport $schedule, ?User $actor = null): void
    {
        $schedule->delete();

        ActivityLogger::log(ActivityModule::Report, ActivityAction::Deleted, null, [
            'type' => 'report_schedule_deleted',
            'report' => $schedule->report,
            'name' => $schedule->name,
        ], causer: $actor ?? false);
    }

    /**
     * Run one schedule for the last complete period before $at.
     *
     * With $advance (the scheduler's path) the schedule moves on to its next
     * run; a manual "run now" leaves the cadence untouched.
     */
    public function runSchedule(ScheduledReport $schedule, ?CarbonInterface $at = null, bool $advance = true): ?GeneratedReport
    {
        $at ??= Date::now();
        $definition = $this->registry->report($schedule->report);

        if ($definition === null) {
            // A retired definition must not keep firing (and failing) forever.
            $schedule->update(['is_active' => false]);

            return null;
        }

        $report = $this->request(
            $definition,
            $schedule->frequency->periodBefore($at),
            $schedule->filters ?? [],
            $schedule->format,
            $schedule->creator,
            $schedule,
        );

        $schedule->fill([
            'last_run_at' => $at,
            ...($advance ? ['next_run_at' => $schedule->nextRunAfter($at)] : []),
        ])->save();

        return $report;
    }

    /** Run every active schedule that has come due. Returns how many ran. */
    public function runDueSchedules(?CarbonInterface $at = null): int
    {
        $at ??= Date::now();
        $ran = 0;

        ScheduledReport::query()->due($at)->with('creator')->each(function (ScheduledReport $schedule) use ($at, &$ran): void {
            if ($this->runSchedule($schedule, $at) !== null) {
                $ran++;
            }
        });

        return $ran;
    }

    private function purge(GeneratedReport $report): void
    {
        if ($report->file_path !== null) {
            Storage::delete($report->file_path);
        }

        $report->delete();
    }
}
