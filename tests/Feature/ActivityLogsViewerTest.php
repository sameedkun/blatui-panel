<?php

namespace Tests\Feature;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityLogName;
use App\Enum\ActivityModule;
use App\Jobs\ExportActivityLog;
use App\Livewire\Admin\Administration\ActivityLogs\Index;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ActivityLogsViewerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin->assignRole($role);

        $this->actingAs($admin);

        return $admin;
    }

    private function seedPanelPermissions(): void
    {
        foreach (['panel.access-admin', 'activity_logs.view', 'activity_logs.export'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    public function test_route_is_forbidden_without_view_permission(): void
    {
        $this->seedPanelPermissions();

        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $staff->givePermissionTo('panel.access-admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($staff)
            ->get(route('admin.activity-logs.index'))
            ->assertForbidden();
    }

    public function test_route_is_accessible_with_view_permission(): void
    {
        $this->seedPanelPermissions();

        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $staff->givePermissionTo(['panel.access-admin', 'activity_logs.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($staff)
            ->get(route('admin.activity-logs.index'))
            ->assertOk()
            ->assertSeeLivewire(Index::class);
    }

    public function test_it_filters_by_module_via_the_properties_json(): void
    {
        $this->actingAsSuperAdmin();
        $target = User::factory()->app()->create();

        ActivityLogger::log(ActivityModule::User, ActivityAction::Banned, $target);
        ActivityLogger::log(ActivityModule::Role, ActivityAction::Updated);

        Livewire::test(Index::class)
            ->set('filters.module', ['user'])
            ->assertViewHas('activities', function ($activities): bool {
                return $activities->count() === 1
                    && $activities->first()->event === ActivityAction::Banned->value;
            });
    }

    public function test_it_filters_by_action_event(): void
    {
        $this->actingAsSuperAdmin();

        ActivityLogger::log(ActivityModule::User, ActivityAction::Banned);
        ActivityLogger::log(ActivityModule::User, ActivityAction::Created);

        Livewire::test(Index::class)
            ->set('filters.action', ['banned'])
            ->assertViewHas('activities', fn ($activities): bool => $activities->count() === 1);
    }

    public function test_search_matches_properties(): void
    {
        $this->actingAsSuperAdmin();

        ActivityLogger::log(ActivityModule::User, ActivityAction::Banned, null, ['ban_reason' => 'needle-spamming']);
        ActivityLogger::log(ActivityModule::User, ActivityAction::Created, null, ['note' => 'something-else']);

        Livewire::test(Index::class)
            ->set('search', 'needle-spamming')
            ->assertViewHas('activities', fn ($activities): bool => $activities->count() === 1);
    }

    public function test_view_activity_opens_the_deep_linked_detail(): void
    {
        $this->actingAsSuperAdmin();
        $target = User::factory()->app()->create();

        ActivityLogger::log(ActivityModule::User, ActivityAction::Banned, $target);
        $activity = Activity::firstOrFail();

        Livewire::test(Index::class)
            ->call('viewActivity', $activity->id)
            ->assertDispatched('open-dialog-activity-detail')
            ->assertSet('selectedId', $activity->id)
            ->assertViewHas('selectedActivity', fn ($selected): bool => $selected?->id === $activity->id);
    }

    public function test_detail_modal_renders_a_before_after_diff(): void
    {
        $this->actingAsSuperAdmin();
        $target = User::factory()->create(['type' => 'staff', 'banned_at' => null]);

        ActivityLogger::log(ActivityModule::Staff, ActivityAction::Updated, $target, [
            'attributes' => ['name' => 'New Name'],
            'old' => ['name' => 'Old Name'],
            'roles' => ['attributes' => ['editor'], 'old' => ['viewer']],
            'password_changed' => true,
        ]);
        $activity = Activity::firstOrFail();

        Livewire::test(Index::class)
            ->call('viewActivity', $activity->id)
            ->assertOk()
            ->assertSee('Changes')
            ->assertSee('Old Name')
            ->assertSee('New Name')
            ->assertSee('Password Changed');
    }

    public function test_scope_to_activity_subject_filters_the_list(): void
    {
        $this->actingAsSuperAdmin();
        $target = User::factory()->app()->create();

        ActivityLogger::log(ActivityModule::User, ActivityAction::Banned, $target);
        ActivityLogger::log(ActivityModule::Role, ActivityAction::Updated);
        $activity = Activity::where('event', 'banned')->firstOrFail();

        Livewire::test(Index::class)
            ->call('scopeToActivitySubject', $activity->id)
            ->assertSet('subjectType', User::class)
            ->assertSet('subjectId', $target->id)
            ->assertViewHas('activities', fn ($activities): bool => $activities->count() === 1);
    }

    public function test_security_signals_count_recent_destructive_activity(): void
    {
        $this->actingAsSuperAdmin();

        ActivityLogger::log(ActivityModule::User, ActivityAction::Banned);
        ActivityLogger::log(ActivityModule::User, ActivityAction::Purged);
        ActivityLogger::log(ActivityModule::User, ActivityAction::Failed, logName: ActivityLogName::Authentication);

        Livewire::test(Index::class)
            ->assertViewHas('signals', function (array $signals): bool {
                return $signals['destructive'] === 2 && $signals['failed_logins'] === 1;
            });
    }

    public function test_module_breakdown_groups_counts_by_module(): void
    {
        $this->actingAsSuperAdmin();

        ActivityLogger::log(ActivityModule::User, ActivityAction::Banned);
        ActivityLogger::log(ActivityModule::User, ActivityAction::Created);
        ActivityLogger::log(ActivityModule::Role, ActivityAction::Updated);

        Livewire::test(Index::class)
            ->assertViewHas('breakdown', function (array $breakdown): bool {
                $byValue = collect($breakdown)->keyBy('value');

                // Largest first, only non-zero modules, correct grouped counts.
                return $breakdown[0]['value'] === 'user'
                    && $byValue['user']['count'] === 2
                    && $byValue['role']['count'] === 1
                    && ! $byValue->has('staff');
            })
            ->assertViewHas('breakdownTotal', 3);
    }

    public function test_export_is_forbidden_without_export_permission(): void
    {
        $this->seedPanelPermissions();

        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $staff->givePermissionTo('activity_logs.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($staff);

        Livewire::test(Index::class)
            ->call('export')
            ->assertForbidden();
    }

    public function test_small_export_streams_a_download(): void
    {
        $this->actingAsSuperAdmin();
        ActivityLogger::log(ActivityModule::User, ActivityAction::Banned);

        Livewire::test(Index::class)
            ->call('export')
            ->assertFileDownloaded();
    }

    public function test_large_export_is_queued(): void
    {
        Bus::fake();
        config(['panel.activity_log_export_queue_threshold' => 1]);

        $this->actingAsSuperAdmin();
        ActivityLogger::log(ActivityModule::User, ActivityAction::Banned);
        ActivityLogger::log(ActivityModule::User, ActivityAction::Created);

        Livewire::test(Index::class)
            ->call('export')
            ->assertNoFileDownloaded();

        Bus::assertDispatched(ExportActivityLog::class);
    }

    public function test_queued_export_preserves_the_active_locale(): void
    {
        Bus::fake();
        config(['panel.activity_log_export_queue_threshold' => 0]);
        app()->setLocale('tr');

        $this->actingAsSuperAdmin();
        ActivityLogger::log(ActivityModule::User, ActivityAction::Created);

        Livewire::test(Index::class)->call('export');

        Bus::assertDispatched(
            ExportActivityLog::class,
            fn (ExportActivityLog $job): bool => $job->locale === 'tr',
        );
    }

    public function test_export_job_writes_a_csv_of_the_filtered_set(): void
    {
        Storage::fake('local');

        ActivityLogger::log(ActivityModule::User, ActivityAction::Banned, null, ['ban_reason' => 'noise'], context: ActivityContext::Admin);

        (new ExportActivityLog(['filters' => []], null))->handle();

        $files = Storage::disk('local')->allFiles('exports');
        $this->assertNotEmpty($files);

        $content = Storage::disk('local')->get($files[0]);
        $this->assertStringContainsString('ID,Date', $content);
        $this->assertStringContainsString('banned', $content);
    }
}
