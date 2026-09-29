<?php

namespace Tests\Feature\Admin\Dashboard;

use App\Enum\ReportFormat;
use App\Enum\ReportFrequency;
use App\Enum\ReportStatus;
use App\Jobs\Report\GenerateReport;
use App\Livewire\Admin\Dashboard\Reports;
use App\Models\Report\GeneratedReport;
use App\Models\Report\ScheduledReport;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Queue::fake();
        $this->travelTo(Date::parse('2026-09-28 09:00:00'));
    }

    public function test_the_page_requires_the_reports_permission(): void
    {
        $this->actingAsStaffWith(['users.view']);

        $this->get(route('admin.dashboard.reports'))->assertForbidden();
    }

    public function test_generating_a_report_queues_it_for_the_requester(): void
    {
        $staff = $this->actingAsStaffWith(['dashboard.reports.view', 'dashboard.reports.create', 'users.view']);

        Livewire::test(Reports::class)
            ->call('openGenerate', 'users')
            ->assertSet('reportKey', 'users')
            ->assertSet('from', '2026-08-30')
            ->assertSet('to', '2026-09-28')
            ->call('applyPreset', 'last_month')
            ->assertSet('from', '2026-08-01')
            ->assertSet('to', '2026-08-31')
            ->set('filters', ['type' => 'guest'])
            ->set('format', 'xlsx')
            ->call('generate')
            ->assertHasNoErrors()
            ->assertDispatched('close-dialog-generate-report');

        $report = GeneratedReport::query()->sole();
        $this->assertSame('users', $report->report);
        $this->assertSame(ReportFormat::Xlsx, $report->format);
        $this->assertSame(['type' => 'guest'], $report->filters);
        $this->assertSame($staff->id, $report->requested_by);
        $this->assertSame('2026-08-01', $report->range_start->toDateString());
        Queue::assertPushed(GenerateReport::class);
    }

    public function test_generation_rejects_reports_the_viewer_cannot_access_and_invalid_periods(): void
    {
        $this->actingAsStaffWith(['dashboard.reports.view', 'dashboard.reports.create', 'users.view']);

        Livewire::test(Reports::class)
            ->set('reportKey', 'revenue')
            ->set('from', '2026-09-01')
            ->set('to', '2026-09-10')
            ->call('generate')
            ->assertHasErrors(['reportKey'])
            ->set('reportKey', 'users')
            ->set('from', '2026-09-10')
            ->set('to', '2026-09-01')
            ->call('generate')
            ->assertHasErrors(['to'])
            ->set('from', '2024-01-01')
            ->set('to', '2026-09-01')
            ->call('generate')
            ->assertHasErrors(['to'])
            ->set('from', '2026-09-01')
            ->set('to', '2026-12-01')
            ->call('generate')
            ->assertHasErrors(['to']);

        $this->assertSame(0, GeneratedReport::query()->count());
    }

    public function test_generating_requires_the_create_permission(): void
    {
        $this->actingAsStaffWith(['dashboard.reports.view', 'users.view']);

        Livewire::test(Reports::class)
            ->set('reportKey', 'users')
            ->set('from', '2026-09-01')
            ->set('to', '2026-09-10')
            ->call('generate')
            ->assertForbidden();
    }

    public function test_the_list_only_shows_reports_whose_data_the_viewer_may_see(): void
    {
        $this->actingAsStaffWith(['dashboard.reports.view', 'users.view']);
        $visible = GeneratedReport::factory()->create(['report' => 'users']);
        GeneratedReport::factory()->create(['report' => 'revenue']);

        $reports = Livewire::test(Reports::class)->viewData('reports');

        $this->assertSame([$visible->id], $reports->pluck('id')->all());
    }

    public function test_a_completed_report_downloads_and_a_hidden_one_cannot(): void
    {
        $this->actingAsStaffWith(['dashboard.reports.view', 'users.view']);
        Storage::put('reports/users.csv', "id,name\n1,Jane\n");
        $report = GeneratedReport::factory()->completed('reports/users.csv')->create(['report' => 'users', 'title' => 'User growth']);
        $hidden = GeneratedReport::factory()->completed('reports/users.csv')->create(['report' => 'revenue']);

        Livewire::test(Reports::class)
            ->call('download', $report->id)
            ->assertFileDownloaded('user-growth_2026-08-30_2026-09-28.csv');

        // A report whose definition the viewer lacks permission for is out of scope, not just hidden.
        // The exception handler would otherwise render it as a 404, so assert the lookup itself fails.
        $this->withoutExceptionHandling();
        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Reports::class)->call('download', $hidden->id);
    }

    public function test_a_report_whose_file_vanished_reports_an_error_instead_of_crashing(): void
    {
        $this->actingAsStaffWith(['dashboard.reports.view', 'users.view']);
        $report = GeneratedReport::factory()->completed('reports/missing.csv')->create(['report' => 'users']);

        Livewire::test(Reports::class)
            ->call('download', $report->id)
            ->assertNoFileDownloaded()
            ->assertDispatched('toast');
    }

    public function test_deleting_a_report_removes_its_file_and_requires_permission(): void
    {
        Storage::put('reports/a.csv', 'a');
        $report = GeneratedReport::factory()->completed('reports/a.csv')->create(['report' => 'users']);

        $this->actingAsStaffWith(['dashboard.reports.view', 'users.view']);
        Livewire::test(Reports::class)->call('confirmDelete', $report->id)->assertForbidden();

        $this->actingAsStaffWith(['dashboard.reports.view', 'dashboard.reports.delete', 'users.view']);
        Livewire::test(Reports::class)
            ->call('confirmDelete', $report->id)
            ->assertSet('deletingReportId', $report->id)
            ->call('delete');

        $this->assertModelMissing($report);
        Storage::assertMissing('reports/a.csv');
    }

    public function test_a_failed_report_can_be_retried(): void
    {
        $this->actingAsStaffWith(['dashboard.reports.view', 'dashboard.reports.create', 'users.view']);
        $report = GeneratedReport::factory()->failed()->create(['report' => 'users']);

        Livewire::test(Reports::class)->call('retry', $report->id);

        $this->assertSame(ReportStatus::Pending, $report->refresh()->status);
        Queue::assertPushed(GenerateReport::class);
    }

    public function test_managing_schedules(): void
    {
        $staff = $this->actingAsStaffWith(['dashboard.reports.view', 'dashboard.reports.manage', 'users.view']);

        $component = Livewire::test(Reports::class)
            ->call('openSchedule', null, 'users')
            ->assertSet('scheduleReport', 'users')
            ->assertSet('scheduleRecipients', $staff->email)
            ->set('scheduleName', 'Weekly signups')
            ->set('scheduleFrequency', ReportFrequency::Weekly->value)
            ->set('scheduleDayOfWeek', 1)
            ->set('scheduleHour', 7)
            ->set('scheduleRecipients', "ops@example.com,\n Team@Example.com ; ops@example.com")
            ->call('saveSchedule')
            ->assertHasNoErrors()
            ->assertSet('tab', 'scheduled');

        $schedule = ScheduledReport::query()->sole();
        $this->assertSame(['ops@example.com', 'team@example.com'], $schedule->recipients);
        $this->assertSame($staff->id, $schedule->created_by);
        $this->assertSame('2026-10-05 07:00', $schedule->next_run_at->format('Y-m-d H:i'));

        $component->call('toggleSchedule', $schedule->id);
        $this->assertFalse($schedule->refresh()->is_active);

        $component->call('runSchedule', $schedule->id);
        $this->assertSame(1, GeneratedReport::query()->where('scheduled_report_id', $schedule->id)->count());

        $component->call('confirmDeleteSchedule', $schedule->id)->call('deleteSchedule');
        $this->assertModelMissing($schedule);
    }

    public function test_schedules_validate_recipients_and_require_the_manage_permission(): void
    {
        $this->actingAsStaffWith(['dashboard.reports.view', 'dashboard.reports.manage', 'users.view']);

        Livewire::test(Reports::class)
            ->call('openSchedule')
            ->set('scheduleName', 'Broken')
            ->set('scheduleRecipients', 'not-an-email')
            ->call('saveSchedule')
            ->assertHasErrors(['scheduleRecipients']);

        $this->assertSame(0, ScheduledReport::query()->count());

        $this->actingAsStaffWith(['dashboard.reports.view', 'dashboard.reports.create', 'users.view']);
        Livewire::test(Reports::class)->call('openSchedule')->assertForbidden();
    }

    public function test_the_generate_quick_action_opens_the_dialog(): void
    {
        $this->actingAsStaffWith(['dashboard.reports.view', 'dashboard.reports.create', 'users.view']);

        Livewire::withQueryParams(['generate' => 1])
            ->test(Reports::class)
            ->assertDispatched('open-dialog-generate-report')
            ->assertSet('reportKey', 'users');
    }

    /** @param  list<string>  $permissions */
    private function actingAsStaffWith(array $permissions): User
    {
        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $role = Role::firstOrCreate(['name' => 'test-role-'.uniqid(), 'guard_name' => 'web']);

        foreach (['panel.access-admin', ...$permissions] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $staff->assignRole($role);
        $this->actingAs($staff);

        return $staff;
    }
}
