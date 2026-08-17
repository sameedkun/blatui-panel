<?php

namespace Tests\Feature\Admin\Accounts\Users;

use App\Enum\ActivityModule;
use App\Jobs\Account\BulkForceDeleteAccounts;
use App\Jobs\Account\BulkPurgeAccounts;
use App\Livewire\Admin\Management\Users\Index as UsersIndex;
use App\Livewire\Admin\Management\Users\Show;
use App\Models\BlockedIp;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

    public function test_profile_page_title_uses_the_request_locale_and_user_name(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create(['name' => 'Jane Doe']);

        $response = $this->withCookie('locale', 'tr')->get(route('admin.users.show', $user));

        $response->assertOk();
        $response->assertSee('<title>'.__('users.title').' — '.$user->name.' — '.config('app.name').'</title>', false);
    }

    public function test_ticket_tab_empty_state_uses_the_active_locale(): void
    {
        App::setLocale('tr');
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create(['name' => 'Ayşe Yılmaz']);

        Livewire::test(Show::class, ['user' => $user])
            ->set('tab', 'tickets')
            ->assertSee(__('tickets.subtitle_user', ['name' => $user->name]))
            ->assertSee(__('tickets.no_tickets'))
            ->assertSee(__('tickets.no_tickets_user_desc', ['name' => $user->name]))
            ->assertDontSee('tickets.subtitle_user')
            ->assertDontSee('tickets.no_tickets')
            ->assertDontSee('tickets.no_tickets_user_desc');
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

        $component->assertSee('Mar 15, 2024');

        $stats = collect($component->instance()->statCards())->keyBy('label');
        // Every stat card is now real/built — Devices included, since device
        // management shipped. 0 for a freshly created user with no devices yet.
        $this->assertSame('Free', $stats['Plan']['value']);
        $this->assertSame('0', $stats['Devices']['value']);
        $this->assertSame('0', $stats['Activity']['value']);
        $this->assertSame('0', $stats['Tickets']['value']);
        $this->assertSame('Mar 15, 2024', $stats[__('users.fields.created_at')]['value']);
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

    public function test_force_delete_from_profile_cleans_up_related_data(): void
    {
        Storage::fake();
        $this->actingAsSuperAdmin();

        $avatarPath = UploadedFile::fake()->image('avatar.jpg')->store('avatars');
        $user = User::factory()->app()->create(['avatar' => $avatarPath]);
        $token = $user->createToken('test');
        BlockedIp::factory()->forUser($user)->create();
        $user->delete();
        $user->refresh();

        Livewire::test(Show::class, ['user' => $user])
            ->set('forceDeleteId', $user->id)
            ->call('forceDelete')
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        Storage::assertMissing($avatarPath);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseMissing('blocked_ips', ['user_id' => $user->id]);
    }

    public function test_index_row_force_delete_cleans_up_related_data(): void
    {
        Storage::fake();
        $this->actingAsSuperAdmin();

        $avatarPath = UploadedFile::fake()->image('avatar.jpg')->store('avatars');
        $user = User::factory()->app()->create(['avatar' => $avatarPath]);
        $user->delete();

        Livewire::test(UsersIndex::class)
            ->set('forceDeleteId', $user->id)
            ->call('forceDelete');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        Storage::assertMissing($avatarPath);
    }

    /**
     * The raw `whereIn(...)->forceDelete()` this bulk action used to run would
     * also throw a foreign-key violation here — `blocked_ips.user_id` is
     * restrictOnDelete(), so a selected user with a block on record would abort
     * the whole batch. Routing each row through DeletionService is what avoids
     * both that and the orphaned avatar/token files.
     */
    public function test_bulk_force_delete_cleans_up_related_data_for_every_selected_user(): void
    {
        Storage::fake();
        $this->actingAsSuperAdmin();

        $pathA = UploadedFile::fake()->image('a.jpg')->store('avatars');
        $pathB = UploadedFile::fake()->image('b.jpg')->store('avatars');
        $userA = User::factory()->app()->create(['avatar' => $pathA]);
        $userB = User::factory()->app()->create(['avatar' => $pathB]);
        BlockedIp::factory()->forUser($userA)->create();
        $userA->delete();
        $userB->delete();

        Livewire::test(UsersIndex::class)
            ->set('tab', 'trashed')
            ->set('selectedIds', collect([$userA->id, $userB->id])->map(fn ($id) => (string) $id)->all())
            ->call('executeBulkForceDelete');

        $this->assertDatabaseMissing('users', ['id' => $userA->id]);
        $this->assertDatabaseMissing('users', ['id' => $userB->id]);
        Storage::assertMissing($pathA);
        Storage::assertMissing($pathB);
        $this->assertDatabaseMissing('blocked_ips', ['user_id' => $userA->id]);

        $row = Activity::where('event', 'force_deleted')->whereNull('subject_id')->latest('id')->firstOrFail();
        $this->assertTrue($row->properties['bulk']);
        $this->assertSame(2, $row->properties['count']);
    }

    public function test_bulk_force_delete_dispatches_a_queued_job_once_selection_exceeds_the_threshold(): void
    {
        Queue::fake();
        config(['panel.bulk_account_action_queue_threshold' => 1]);
        $admin = $this->actingAsSuperAdmin();

        $userA = User::factory()->app()->create();
        $userB = User::factory()->app()->create();
        $userA->delete();
        $userB->delete();

        Livewire::test(UsersIndex::class)
            ->set('tab', 'trashed')
            ->set('selectedIds', collect([$userA->id, $userB->id])->map(fn ($id) => (string) $id)->all())
            ->call('executeBulkForceDelete')
            ->assertDispatched('toast', type: 'success');

        // Nothing runs inline once queued — the accounts must still exist.
        $this->assertNotNull(User::withTrashed()->find($userA->id));
        $this->assertNotNull(User::withTrashed()->find($userB->id));

        Queue::assertPushed(BulkForceDeleteAccounts::class, function (BulkForceDeleteAccounts $job) use ($userA, $userB, $admin): bool {
            return $job->userIds === [(string) $userA->id, (string) $userB->id]
                && $job->module === ActivityModule::User
                && $job->requestedBy === $admin->id;
        });
    }

    public function test_bulk_instant_purge_dispatches_a_queued_job_once_selection_exceeds_the_threshold(): void
    {
        Queue::fake();
        config(['panel.bulk_account_action_queue_threshold' => 1]);
        $admin = $this->actingAsSuperAdmin();

        $userA = User::factory()->app()->create();
        $userB = User::factory()->app()->create();

        Livewire::test(UsersIndex::class)
            ->set('selectedIds', collect([$userA->id, $userB->id])->map(fn ($id) => (string) $id)->all())
            ->set('bulkPurgeReason', 'Bulk cleanup')
            ->call('executeBulkInstantPurge')
            ->assertDispatched('toast', type: 'success');

        $this->assertNotNull(User::withTrashed()->find($userA->id));
        $this->assertNotNull(User::withTrashed()->find($userB->id));

        Queue::assertPushed(BulkPurgeAccounts::class, function (BulkPurgeAccounts $job) use ($userA, $userB, $admin): bool {
            return $job->userIds === [(string) $userA->id, (string) $userB->id]
                && $job->type === 'app'
                && $job->reason === 'Bulk cleanup'
                && $job->requestedBy === $admin->id;
        });
    }

    /**
     * The queue threshold only decides sync vs. async — this cap is what
     * actually stops an unbounded selection from being handed to anything at
     * all, queued included.
     */
    public function test_bulk_force_delete_rejects_a_selection_over_the_max_cap(): void
    {
        Queue::fake();
        config(['panel.bulk_account_action_max_selection' => 1]);
        $this->actingAsSuperAdmin();

        $userA = User::factory()->app()->create();
        $userB = User::factory()->app()->create();
        $userA->delete();
        $userB->delete();

        Livewire::test(UsersIndex::class)
            ->set('tab', 'trashed')
            ->set('selectedIds', collect([$userA->id, $userB->id])->map(fn ($id) => (string) $id)->all())
            ->call('executeBulkForceDelete')
            ->assertDispatched('toast', type: 'error');

        // Selection is left intact so the admin can trim and retry.
        $this->assertNotNull(User::withTrashed()->find($userA->id));
        $this->assertNotNull(User::withTrashed()->find($userB->id));
        Queue::assertNotPushed(BulkForceDeleteAccounts::class);
    }

    public function test_bulk_instant_purge_rejects_a_selection_over_the_max_cap(): void
    {
        Queue::fake();
        config(['panel.bulk_account_action_max_selection' => 1]);
        $this->actingAsSuperAdmin();

        $userA = User::factory()->app()->create();
        $userB = User::factory()->app()->create();

        Livewire::test(UsersIndex::class)
            ->set('selectedIds', collect([$userA->id, $userB->id])->map(fn ($id) => (string) $id)->all())
            ->call('executeBulkInstantPurge')
            ->assertDispatched('toast', type: 'error');

        $this->assertNotNull(User::withTrashed()->find($userA->id));
        $this->assertNotNull(User::withTrashed()->find($userB->id));
        Queue::assertNotPushed(BulkPurgeAccounts::class);
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
