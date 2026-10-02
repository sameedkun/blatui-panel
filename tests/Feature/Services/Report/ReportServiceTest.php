<?php

namespace Tests\Feature\Services\Report;

use App\Enum\ReportFormat;
use App\Enum\ReportFrequency;
use App\Enum\ReportSource;
use App\Enum\ReportStatus;
use App\Jobs\Report\GenerateReport;
use App\Mail\Report\ReportReadyMail;
use App\Models\Report\GeneratedReport;
use App\Models\Report\ScheduledReport;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Report\ReportService;
use App\Support\Dashboard\DashboardRegistry;
use App\Support\Dashboard\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->travelTo(Date::parse('2026-09-28 09:00:00'));
    }

    public function test_requesting_a_report_records_it_queues_generation_and_audits_it(): void
    {
        Queue::fake();
        $requester = User::factory()->create(['type' => 'staff']);
        $definition = $this->registry()->report('revenue');

        $report = $this->service()->request($definition, DateRange::preset('30d'), ['plan' => 'not-a-plan', 'bogus' => 'x'], ReportFormat::Pdf, $requester);

        $this->assertSame(ReportStatus::Pending, $report->status);
        $this->assertSame(ReportSource::Manual, $report->source);
        $this->assertSame($requester->id, $report->requested_by);
        $this->assertNull($report->filters, 'Unknown filters and invalid values are dropped.');
        Queue::assertPushed(GenerateReport::class, fn (GenerateReport $job): bool => $job->reportId === $report->id);

        $this->assertTrue(Activity::query()->where('event', 'created')->where('properties->type', 'report_generated')->exists());
    }

    #[DataProvider('definitionsAndFormats')]
    public function test_every_report_definition_generates_in_every_format(string $key, ReportFormat $format): void
    {
        Queue::fake();
        $this->seedData();

        $report = $this->service()->request($this->registry()->report($key), DateRange::preset('30d'), [], $format);
        $this->service()->generate($report->refresh());
        $report->refresh();

        $this->assertSame(ReportStatus::Completed, $report->status, (string) $report->error);
        $this->assertSame("reports/{$report->ulid}.{$format->value}", $report->file_path);
        Storage::assertExists($report->file_path);
        $this->assertSame(strlen(Storage::get($report->file_path)), $report->file_size);
        $this->assertNotNull($report->row_count);
        $this->assertTrue($report->expires_at->isSameDay(now()->addDays(30)));
    }

    /** @return array<string, array{0: string, 1: ReportFormat}> */
    public static function definitionsAndFormats(): array
    {
        $cases = [];

        foreach (['users', 'revenue', 'subscription_summary', 'tickets', 'support_performance', 'security_events'] as $key) {
            foreach (ReportFormat::cases() as $format) {
                $cases["{$key} {$format->value}"] = [$key, $format];
            }
        }

        return $cases;
    }

    public function test_generated_csv_contains_the_filtered_dataset(): void
    {
        Queue::fake();
        $this->seedData();
        User::factory()->guest()->create(['name' => 'Guesty McGuest', 'created_at' => now()->subDay()]);

        $report = $this->service()->request($this->registry()->report('users'), DateRange::preset('30d'), ['type' => 'guest'], ReportFormat::Csv);
        $this->service()->generate($report->refresh());

        $csv = Storage::get($report->refresh()->file_path);

        $this->assertSame(1, $report->row_count);
        $this->assertStringContainsString('Guesty McGuest', $csv);
    }

    public function test_a_failing_definition_marks_the_report_failed_without_throwing(): void
    {
        $report = GeneratedReport::factory()->create(['report' => 'retired-report']);

        $this->service()->generate($report);

        $report->refresh();
        $this->assertSame(ReportStatus::Failed, $report->status);
        $this->assertStringContainsString('retired-report', $report->error);
        $this->assertNull($report->file_path);
    }

    public function test_retry_requeues_only_failed_reports(): void
    {
        Queue::fake();
        $failed = GeneratedReport::factory()->failed()->create();
        $completed = GeneratedReport::factory()->completed()->create();

        $this->service()->retry($failed);
        $this->service()->retry($completed);

        $this->assertSame(ReportStatus::Pending, $failed->refresh()->status);
        $this->assertNull($failed->error);
        $this->assertSame(ReportStatus::Completed, $completed->refresh()->status);
        Queue::assertPushed(GenerateReport::class, 1);
    }

    public function test_deleting_and_pruning_remove_both_the_file_and_the_row(): void
    {
        Storage::put('reports/a.csv', 'a');
        Storage::put('reports/b.csv', 'b');
        Storage::put('reports/c.csv', 'c');
        $deleted = GeneratedReport::factory()->completed('reports/a.csv')->create();
        $expired = GeneratedReport::factory()->completed('reports/b.csv')->create(['expires_at' => now()->subDay()]);
        $kept = GeneratedReport::factory()->completed('reports/c.csv')->create();

        $this->service()->delete($deleted);
        $this->assertSame(1, $this->service()->pruneExpired());

        Storage::assertMissing(['reports/a.csv', 'reports/b.csv']);
        Storage::assertExists('reports/c.csv');
        $this->assertModelMissing($deleted);
        $this->assertModelMissing($expired);
        $this->assertModelExists($kept);
    }

    public function test_saving_a_schedule_normalises_it_and_computes_its_next_run(): void
    {
        $schedule = $this->service()->saveSchedule([
            'name' => 'Weekly users',
            'report' => 'users',
            'format' => ReportFormat::Xlsx,
            'filters' => ['type' => 'app', 'junk' => 'x'],
            'frequency' => ReportFrequency::Weekly,
            'day_of_week' => 1,
            'day_of_month' => 15,
            'hour' => 8,
            'recipients' => ['team@example.com'],
        ]);

        $this->assertSame(['type' => 'app'], $schedule->filters);
        $this->assertNull($schedule->day_of_month, 'Only the field the frequency uses is kept.');
        $this->assertSame('2026-10-05 08:00', $schedule->next_run_at->format('Y-m-d H:i'));
    }

    public function test_due_schedules_run_for_the_last_complete_period_and_advance(): void
    {
        Queue::fake();
        $due = ScheduledReport::factory()->due()->create(['frequency' => ReportFrequency::Monthly, 'day_of_month' => 1, 'hour' => 8]);
        ScheduledReport::factory()->create();
        ScheduledReport::factory()->due()->paused()->create();

        $this->assertSame(1, $this->service()->runDueSchedules());

        $report = GeneratedReport::query()->sole();
        $this->assertSame(ReportSource::Scheduled, $report->source);
        $this->assertSame($due->id, $report->scheduled_report_id);
        $this->assertSame($due->name, $report->title);
        $this->assertSame('2026-08-01', $report->range_start->toDateString());
        $this->assertSame('2026-08-31', $report->range_end->toDateString());

        $due->refresh();
        $this->assertSame('2026-10-01 08:00', $due->next_run_at->format('Y-m-d H:i'));
        $this->assertTrue($due->last_run_at->equalTo(now()));
    }

    public function test_running_a_schedule_manually_keeps_its_cadence(): void
    {
        Queue::fake();
        $schedule = ScheduledReport::factory()->create(['next_run_at' => '2026-10-01 08:00:00']);

        $this->service()->runSchedule($schedule, advance: false);

        $this->assertSame('2026-10-01 08:00', $schedule->refresh()->next_run_at->format('Y-m-d H:i'));
        $this->assertSame(1, GeneratedReport::query()->count());
    }

    public function test_a_schedule_for_a_retired_report_is_deactivated_instead_of_failing_forever(): void
    {
        $schedule = ScheduledReport::factory()->due()->create(['report' => 'retired-report']);

        $this->assertSame(0, $this->service()->runDueSchedules());
        $this->assertFalse($schedule->refresh()->is_active);
    }

    public function test_a_completed_scheduled_report_is_emailed_with_the_file_attached(): void
    {
        Mail::fake();
        Queue::fake();
        $schedule = ScheduledReport::factory()->create(['recipients' => ['a@example.com', 'b@example.com']]);

        $report = $this->service()->runSchedule($schedule);
        $this->service()->generate($report->refresh());

        Mail::assertSent(ReportReadyMail::class, function (ReportReadyMail $mail) use ($report): bool {
            return $mail->hasTo('a@example.com')
                && $mail->hasTo('b@example.com')
                && $mail->attachFile
                && $mail->report->is($report)
                && count($mail->attachments()) === 1;
        });
    }

    public function test_an_oversized_scheduled_report_is_linked_instead_of_attached(): void
    {
        Mail::fake();
        Queue::fake();
        config()->set('dashboard.reports.max_attachment_kb', 0);
        $schedule = ScheduledReport::factory()->create();

        $report = $this->service()->runSchedule($schedule);
        $this->service()->generate($report->refresh());

        Mail::assertSent(ReportReadyMail::class, fn (ReportReadyMail $mail): bool => ! $mail->attachFile && $mail->attachments() === []);
    }

    public function test_the_job_marks_an_unfinished_report_failed_when_the_worker_gives_up(): void
    {
        $report = GeneratedReport::factory()->create(['status' => ReportStatus::Processing]);

        (new GenerateReport($report->id))->failed(new RuntimeException('Timed out.'));

        $this->assertSame(ReportStatus::Failed, $report->refresh()->status);
        $this->assertSame('Timed out.', $report->error);
    }

    public function test_the_job_ignores_a_report_deleted_while_queued(): void
    {
        (new GenerateReport(999))->handle($this->service());

        $this->assertSame(0, GeneratedReport::query()->count());
    }

    private function seedData(): void
    {
        $agent = User::factory()->create(['type' => 'staff']);
        User::factory()->count(3)->create(['type' => 'app', 'created_at' => now()->subDays(3)]);
        $subscription = Subscription::factory()->create(['starts_at' => now()->subDays(2)]);
        SubscriptionTransaction::factory()->for($subscription)->amount(2000)->create(['purchased_at' => now()->subDays(2)]);
        Ticket::factory()->create(['assigned_to' => $agent->id, 'created_at' => now()->subDays(2)]);
    }

    private function service(): ReportService
    {
        return app(ReportService::class);
    }

    private function registry(): DashboardRegistry
    {
        return app(DashboardRegistry::class);
    }
}
