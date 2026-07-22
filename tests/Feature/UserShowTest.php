<?php

namespace Tests\Feature;

use App\Livewire\Admin\Management\Users\Index as UsersIndex;
use App\Livewire\Admin\Management\Users\Show;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserShowTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    /** Staff granted exactly the given abilities (plus panel access). */
    private function staffWith(array $abilities): User
    {
        foreach (['panel.access-admin', 'users.view', 'users.manage', 'users.edit', 'users.ban', 'users.unban', 'users.delete', 'users.restore', 'users.force-delete', 'activity_logs.view'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $staff->givePermissionTo(array_merge(['panel.access-admin'], $abilities));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    public function test_page_403s_without_users_view(): void
    {
        $this->actingAs($this->staffWith([])); // panel access only
        $user = User::factory()->app()->create();

        Livewire::test(Show::class, ['user' => $user])->assertForbidden();
    }

    public function test_header_actions_respect_their_own_permission(): void
    {
        // Has view/manage but NOT ban / delete / force-delete.
        $this->actingAs($this->staffWith(['users.view', 'users.manage']));
        $user = User::factory()->app()->create();

        Livewire::test(Show::class, ['user' => $user])
            ->call('openBanDialog', $user->id)->assertForbidden();

        Livewire::test(Show::class, ['user' => $user])
            ->call('openScheduleDeletionDialog', $user->id)->assertForbidden();

        Livewire::test(Show::class, ['user' => $user])
            ->call('confirmInstantPurge', $user->id)->assertForbidden();
    }

    public function test_header_ban_matches_an_index_ban_on_every_axis(): void
    {
        $this->actingAsSuperAdmin();
        $fromIndex = User::factory()->app()->create();
        $fromProfile = User::factory()->app()->create();

        Livewire::test(UsersIndex::class)
            ->set('banningUserId', $fromIndex->id)
            ->set('banReason', 'spam')
            ->call('confirmBan');

        Livewire::test(Show::class, ['user' => $fromProfile])
            ->call('openBanDialog', $fromProfile->id)
            ->set('banReason', 'spam')
            ->call('confirmBan');

        $indexRow = Activity::where('subject_id', $fromIndex->id)->where('event', 'banned')->firstOrFail();
        $profileRow = Activity::where('subject_id', $fromProfile->id)->where('event', 'banned')->firstOrFail();

        // Identical on every axis — proves the action was reused, not reimplemented.
        $this->assertSame($indexRow->log_name, $profileRow->log_name);
        $this->assertSame($indexRow->event, $profileRow->event);
        $this->assertSame($indexRow->subject_type, $profileRow->subject_type);
        $this->assertSame($indexRow->properties['module'], $profileRow->properties['module']);
        $this->assertSame($indexRow->properties['context'], $profileRow->properties['context']);
        $this->assertSame('user', $profileRow->properties['module']);
        $this->assertSame('banned', $profileRow->event);
    }

    public function test_stats_show_real_joined_date_and_placeholders_never_fake_zeros(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create([
            'registration_date' => now()->parse('2024-03-15'),
        ]);

        $component = Livewire::test(Show::class, ['user' => $user]);

        // Joined is real; the unbuilt module cards say "Coming soon", not "0".
        $component->assertSee('Mar 15, 2024');
        $component->assertSee('Coming soon');

        $stats = collect($component->instance()->statCards())->keyBy('label');
        $this->assertNull($stats['Subscriptions']['value']);
        $this->assertNull($stats['Devices']['value']);
        $this->assertNull($stats['Tickets']['value']);
        // Activity is a real count (not a placeholder) — 0 for a freshly created user.
        $this->assertSame('0', $stats['Activity']['value']);
        $this->assertSame('Mar 15, 2024', $stats['Joined']['value']);
    }

    public function test_activity_stat_card_reflects_the_records_audit_trail(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();

        Livewire::test(Show::class, ['user' => $user])
            ->call('openBanDialog', $user->id)
            ->set('banReason', 'spam')
            ->call('confirmBan');

        $component = Livewire::test(Show::class, ['user' => $user]);
        $stats = collect($component->instance()->statCards())->keyBy('label');

        $this->assertSame('1', $stats['Activity']['value']);
    }

    public function test_activity_stat_card_is_a_placeholder_without_activity_logs_permission(): void
    {
        $this->actingAs($this->staffWith(['users.view', 'users.manage']));
        $user = User::factory()->app()->create();

        $component = Livewire::test(Show::class, ['user' => $user]);
        $stats = collect($component->instance()->statCards())->keyBy('label');

        $this->assertNull($stats['Activity']['value']);
    }

    public function test_ban_action_rejects_a_trashed_account_even_if_forged(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $user->delete();
        $user->refresh();

        Livewire::test(Show::class, ['user' => $user])
            ->call('openBanDialog', $user->id)
            ->assertForbidden();
    }

    public function test_schedule_deletion_rejects_an_already_pending_account(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->pendingDeletion('admin')->create();

        Livewire::test(Show::class, ['user' => $user])
            ->call('openScheduleDeletionDialog', $user->id)
            ->assertForbidden();
    }

    public function test_force_delete_from_profile_redirects_to_index(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $user->delete();
        $user->refresh();

        Livewire::test(Show::class, ['user' => $user])
            ->set('forceDeleteId', $user->id)
            ->call('forceDelete')
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_verify_email_manually_marks_the_email_verified(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create(['email_verified_at' => null]);

        Livewire::test(Show::class, ['user' => $user])
            ->call('verifyEmailManually')
            ->assertDispatched('toast', type: 'success');

        $this->assertNotNull($user->fresh()->email_verified_at);

        // Exactly one audit row — logged centrally by AuthActivityListener::handleVerified()
        // off the Verified event, not duplicated by an explicit call here too.
        $activity = Activity::where('event', 'verified')->where('subject_id', $user->id)->get();
        $this->assertCount(1, $activity);
        $this->assertSame($admin->id, $activity->first()->causer_id);
        $this->assertSame('admin', $activity->first()->properties['initiated_by']);
    }

    public function test_verify_email_manually_is_a_no_op_when_already_verified(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create(['email_verified_at' => now()]);

        Livewire::test(Show::class, ['user' => $user])
            ->call('verifyEmailManually')
            ->assertDispatched('toast', type: 'info');
    }

    public function test_resend_verification_email_sends_the_notification(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create(['email_verified_at' => null]);

        Livewire::test(Show::class, ['user' => $user])
            ->call('resendVerificationEmail')
            ->assertDispatched('toast', type: 'success');

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_send_password_reset_link_dispatches_the_notification(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();

        Livewire::test(Show::class, ['user' => $user])
            ->call('sendPasswordResetLink')
            ->assertDispatched('toast', type: 'success');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }
}
