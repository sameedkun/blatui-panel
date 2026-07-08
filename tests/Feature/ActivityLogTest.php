<?php

namespace Tests\Feature;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityModule;
use App\Livewire\Admin\Management\Users\Form as UserForm;
use App\Livewire\Admin\Management\Users\Index as UsersIndex;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Support\ActivityLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    /** A staff user with the super-admin role (Gate::before grants every permission). */
    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin->assignRole($role);

        $this->actingAs($admin);

        return $admin;
    }

    public function test_admin_ban_is_logged_on_the_four_axes(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $target = User::factory()->app()->create();

        Livewire::test(UsersIndex::class)
            ->set('banningUserId', $target->id)
            ->set('banReason', 'spam')
            ->call('confirmBan');

        $activity = Activity::where('event', 'banned')->firstOrFail();
        $this->assertSame('audit', $activity->log_name);           // category
        $this->assertSame('user', $activity->properties['module']); // module
        $this->assertSame('banned', $activity->event);              // action
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame($target->id, $activity->subject_id);
        $this->assertSame(User::class, $activity->subject_type);    // real model, not module
        $this->assertSame('spam', $activity->properties['ban_reason']);
    }

    public function test_user_edit_logs_a_sanitized_old_new_diff(): void
    {
        $this->actingAsSuperAdmin();
        $target = User::factory()->app()->create(['name' => 'Old Name']);

        Livewire::test(UserForm::class, ['user' => $target])
            ->set('name', 'New Name')
            ->set('email', $target->email)
            ->call('save');

        $activity = Activity::where('event', 'updated')->firstOrFail();
        $this->assertSame('user', $activity->properties['module']);
        $this->assertSame('New Name', $activity->properties['attributes']['name']);
        $this->assertSame('Old Name', $activity->properties['old']['name']);
        $this->assertArrayNotHasKey('password', (array) $activity->properties['attributes']);
    }

    public function test_creating_users_writes_no_activity(): void
    {
        User::factory()->guest()->create();
        User::factory()->app()->create();

        $this->assertSame(0, Activity::count());
    }

    public function test_staff_login_is_logged_but_guest_login_is_not(): void
    {
        $staff = User::factory()->create(['type' => 'staff']);
        $guest = User::factory()->guest()->create();

        event(new Login('web', $staff, false));
        event(new Login('web', $guest, false));

        $logins = Activity::where('event', 'login')->get();
        $this->assertCount(1, $logins);
        $this->assertSame('authentication', $logins->first()->log_name);
        $this->assertSame('staff', $logins->first()->properties['module']);
        $this->assertSame($staff->id, $logins->first()->causer_id);
    }

    public function test_bulk_ban_collapses_to_one_banned_row_flagged_bulk(): void
    {
        $this->actingAsSuperAdmin();
        $a = User::factory()->app()->create();
        $b = User::factory()->app()->create();

        Livewire::test(UsersIndex::class)
            ->set('selectedIds', [(string) $a->id, (string) $b->id])
            ->call('executeBulkBan');

        $rows = Activity::where('event', 'banned')->get();
        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->properties['bulk']);
        $this->assertSame(2, $rows->first()->properties['count']);
    }

    public function test_scheduled_purge_is_subjectless_with_system_causer_and_scheduler_context(): void
    {
        config(['panel.account_deletion_grace_hours' => 24]);
        $user = User::factory()->pendingDeletion('user', now()->subHours(25))->create();

        app(AccountDeletionService::class)->purgeExpired();

        $activity = Activity::where('event', 'purged')->firstOrFail();
        $this->assertSame('audit', $activity->log_name);
        $this->assertNull($activity->causer_id);
        $this->assertNull($activity->subject_id);
        $this->assertSame('user', $activity->properties['module']);
        $this->assertSame('scheduler', $activity->properties['context']);
        $this->assertSame('scheduled', $activity->properties['initiated_by']);
        $this->assertSame($user->email, $activity->properties['snapshot']['email']);
    }

    public function test_context_is_auto_detected_and_can_be_overridden(): void
    {
        // PHPUnit runs in the console runtime, so auto-detection yields Console.
        ActivityLogger::log(ActivityModule::User, ActivityAction::Updated);
        $this->assertSame('console', Activity::latest('id')->first()->properties['context']);

        ActivityLogger::log(ActivityModule::User, ActivityAction::Updated, context: ActivityContext::Api);
        $this->assertSame('api', Activity::latest('id')->first()->properties['context']);
    }

    public function test_activity_logger_accepts_an_explicit_causer_without_an_auth_session(): void
    {
        $actor = User::factory()->create(['type' => 'staff']);

        ActivityLogger::log(ActivityModule::User, ActivityAction::Assigned, causer: $actor);

        $activity = Activity::where('event', 'assigned')->firstOrFail();
        $this->assertSame($actor->id, $activity->causer_id);
    }
}
