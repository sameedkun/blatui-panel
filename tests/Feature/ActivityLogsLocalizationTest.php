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
use App\Support\ActivityPresenter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogsLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(config('panel.super_admin_role'));
        $this->actingAs($admin);

        return $admin;
    }

    public function test_english_and_turkish_activity_log_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('activity_logs', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('activity_logs', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_activity_log_page_and_browser_title_use_the_request_locale(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->withCookie('locale', 'tr')->get(route('admin.activity-logs.index'));

        $response->assertOk();
        $response->assertSee('<title>'.__('activity_logs.title').' — '.config('app.name').'</title>', false);
        $response->assertSee(__('activity_logs.subtitle'));
        $response->assertSee(__('activity_logs.signals.title'));
        $response->assertSee(__('activity_logs.filters.search_placeholder'));
        $response->assertSee(__('activity_logs.table.empty'));
    }

    public function test_enum_labels_and_detail_dialog_use_the_active_locale(): void
    {
        App::setLocale('tr');
        $this->actingAsSuperAdmin();

        $target = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        ActivityLogger::log(ActivityModule::Staff, ActivityAction::Updated, $target, [
            'attributes' => ['name' => 'Yeni Ad'],
            'old' => ['name' => 'Eski Ad'],
            'password_changed' => true,
        ]);
        $activity = Activity::firstOrFail();

        $this->assertSame(__('activity_logs.enums.actions.updated'), ActivityAction::Updated->label());
        $this->assertSame(__('activity_logs.enums.modules.staff'), ActivityModule::Staff->label());
        $this->assertSame(__('activity_logs.enums.contexts.admin'), ActivityContext::Admin->label());
        $this->assertSame(__('activity_logs.enums.categories.audit'), ActivityLogName::Audit->label());
        $this->assertSame(__('activity_logs.presenter.titles.updated'), ActivityPresenter::present($activity, $target)['title']);

        Livewire::test(Index::class)
            ->call('viewActivity', $activity->id)
            ->assertSee(__('activity_logs.detail.changes'))
            ->assertSee(__('activity_logs.detail.before'))
            ->assertSee(__('activity_logs.detail.after'))
            ->assertSee(__('activity_logs.fields.name'))
            ->assertSee(__('activity_logs.enums.actions.updated'))
            ->assertSee(__('activity_logs.enums.modules.staff'));
    }

    public function test_queued_export_preserves_locale_and_uses_localized_feedback(): void
    {
        App::setLocale('tr');
        Bus::fake();
        config(['panel.activity_log_export_queue_threshold' => 0]);

        $admin = $this->actingAsSuperAdmin();
        ActivityLogger::log(ActivityModule::User, ActivityAction::Created);

        Livewire::test(Index::class)
            ->call('export')
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('activity_logs.messages.export_queued'),
            );

        Bus::assertDispatched(
            ExportActivityLog::class,
            fn (ExportActivityLog $job): bool => $job->requestedBy === $admin->id && $job->locale === 'tr',
        );
    }

    public function test_queued_export_writes_localized_csv_headers(): void
    {
        Storage::fake('local');

        (new ExportActivityLog(['filters' => []], null, 'tr'))->handle();

        $files = Storage::disk('local')->allFiles('exports');
        $this->assertNotEmpty($files);

        $content = Storage::disk('local')->get($files[0]);
        $this->assertStringContainsString('Tarih,Kategori,Modül,Eylem', $content);
    }
}
