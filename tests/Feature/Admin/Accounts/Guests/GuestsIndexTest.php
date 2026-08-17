<?php

namespace Tests\Feature\Admin\Accounts\Guests;

use App\Enum\ActivityModule;
use App\Jobs\Account\BulkForceDeleteAccounts;
use App\Jobs\Account\BulkPurgeAccounts;
use App\Livewire\Admin\Management\Guests\Index;
use App\Models\BlockedIp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestsIndexTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    public function test_english_and_turkish_guest_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('guests', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('guests', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_index_page_uses_the_request_locale(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->withCookie('locale', 'tr')->get(route('admin.guests.index'));

        $response->assertOk();
        $response->assertSee('<title>'.__('guests.title').' — '.config('app.name').'</title>', false);
        $response->assertSee(__('guests.subtitle'));
    }

    public function test_default_ban_reason_and_toast_use_the_active_locale(): void
    {
        App::setLocale('tr');
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        Livewire::test(Index::class)
            ->set('banningUserId', $guest->id)
            ->call('confirmBan')
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('guests.toasts.guest_banned', ['name' => $guest->name]),
            );

        $this->assertSame(__('guests.defaults.ban_reason'), $guest->fresh()->ban_reason);
    }

    public function test_ban_writes_an_activity_log_row(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        Livewire::test(Index::class)
            ->set('banningUserId', $guest->id)
            ->set('banReason', 'spam')
            ->call('confirmBan');

        $this->assertTrue($guest->fresh()->isBanned());

        $row = Activity::where('subject_id', $guest->id)->where('event', 'banned')->firstOrFail();
        $this->assertSame('guest', $row->properties['module']);
    }

    public function test_unban_writes_an_activity_log_row(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create(['banned_at' => now(), 'ban_reason' => 'spam']);

        Livewire::test(Index::class)->call('unban', $guest->id);

        $this->assertFalse($guest->fresh()->isBanned());

        $row = Activity::where('subject_id', $guest->id)->where('event', 'unbanned')->firstOrFail();
        $this->assertSame('guest', $row->properties['module']);
    }

    public function test_delete_permanently_purges_the_guest_and_writes_an_activity_log_row(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        Livewire::test(Index::class)
            ->set('deletingId', $guest->id)
            ->call('delete');

        $this->assertDatabaseMissing('users', ['id' => $guest->id]);

        $row = Activity::where('event', 'purged')->latest('id')->firstOrFail();
        $this->assertSame('guest', $row->properties['module']);
        $this->assertSame($guest->id, $row->properties['snapshot']['id']);
    }

    public function test_restore_writes_an_activity_log_row(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create(['banned_at' => null]);
        $guest->delete();
        $guest->refresh();

        Livewire::test(Index::class)
            ->set('restoringId', $guest->id)
            ->call('restore');

        $this->assertFalse($guest->fresh()->trashed());

        $row = Activity::where('subject_id', $guest->id)->where('event', 'restored')->firstOrFail();
        $this->assertSame('guest', $row->properties['module']);
    }

    public function test_force_delete_writes_an_activity_log_row(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create(['banned_at' => null]);
        $guest->delete();
        $guest->refresh();

        Livewire::test(Index::class)
            ->set('forceDeleteId', $guest->id)
            ->call('forceDelete');

        $this->assertDatabaseMissing('users', ['id' => $guest->id]);

        $row = Activity::where('event', 'force_deleted')->latest('id')->firstOrFail();
        $this->assertSame('guest', $row->properties['module']);
    }

    public function test_force_delete_cleans_up_related_data(): void
    {
        Storage::fake();
        $this->actingAsSuperAdmin();

        $avatarPath = UploadedFile::fake()->image('avatar.jpg')->store('avatars');
        $guest = User::factory()->guest()->create(['banned_at' => null, 'avatar' => $avatarPath]);
        $guest->delete();

        Livewire::test(Index::class)
            ->set('forceDeleteId', $guest->id)
            ->call('forceDelete');

        $this->assertDatabaseMissing('users', ['id' => $guest->id]);
        Storage::assertMissing($avatarPath);
    }

    public function test_bulk_ban_writes_a_single_bulk_activity_log_row(): void
    {
        $this->actingAsSuperAdmin();
        $guests = User::factory()->guest()->count(2)->create(['banned_at' => null]);

        Livewire::test(Index::class)
            ->set('selectedIds', $guests->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->call('executeBulkBan');

        $this->assertTrue($guests->fresh()->every(fn (User $g) => $g->isBanned()));

        $row = Activity::where('event', 'banned')->whereNull('subject_id')->latest('id')->firstOrFail();
        $this->assertTrue($row->properties['bulk']);
        $this->assertSame('guest', $row->properties['module']);
        $this->assertSame(2, $row->properties['count']);
    }

    public function test_bulk_delete_permanently_purges_every_selected_guest(): void
    {
        $this->actingAsSuperAdmin();
        $guests = User::factory()->guest()->count(2)->create(['banned_at' => null]);

        Livewire::test(Index::class)
            ->set('selectedIds', $guests->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->call('executeBulkDelete');

        foreach ($guests as $guest) {
            $this->assertDatabaseMissing('users', ['id' => $guest->id]);
        }

        $this->assertSame(2, Activity::where('event', 'purged')->count());
    }

    /**
     * The raw `whereIn(...)->forceDelete()` this bulk action used to run would
     * also throw a foreign-key violation here — `blocked_ips.user_id` is
     * restrictOnDelete(), so a selected guest with a block on record would
     * abort the whole batch. Routing each row through DeletionService avoids
     * both that and the orphaned avatar/token files.
     */
    public function test_bulk_force_delete_cleans_up_related_data_for_every_selected_guest(): void
    {
        Storage::fake();
        $this->actingAsSuperAdmin();

        $pathA = UploadedFile::fake()->image('a.jpg')->store('avatars');
        $pathB = UploadedFile::fake()->image('b.jpg')->store('avatars');
        $guestA = User::factory()->guest()->create(['banned_at' => null, 'avatar' => $pathA]);
        $guestB = User::factory()->guest()->create(['banned_at' => null, 'avatar' => $pathB]);
        BlockedIp::factory()->forUser($guestA)->create();
        $guestA->delete();
        $guestB->delete();

        Livewire::test(Index::class)
            ->set('selectedIds', collect([$guestA->id, $guestB->id])->map(fn ($id) => (string) $id)->all())
            ->call('executeBulkForceDelete');

        $this->assertDatabaseMissing('users', ['id' => $guestA->id]);
        $this->assertDatabaseMissing('users', ['id' => $guestB->id]);
        Storage::assertMissing($pathA);
        Storage::assertMissing($pathB);
        $this->assertDatabaseMissing('blocked_ips', ['user_id' => $guestA->id]);

        $row = Activity::where('event', 'force_deleted')->whereNull('subject_id')->latest('id')->firstOrFail();
        $this->assertTrue($row->properties['bulk']);
        $this->assertSame(2, $row->properties['count']);
    }

    public function test_bulk_delete_dispatches_a_queued_job_once_selection_exceeds_the_threshold(): void
    {
        Queue::fake();
        config(['panel.bulk_account_action_queue_threshold' => 1]);
        $admin = $this->actingAsSuperAdmin();

        $guestA = User::factory()->guest()->create(['banned_at' => null]);
        $guestB = User::factory()->guest()->create(['banned_at' => null]);

        Livewire::test(Index::class)
            ->set('selectedIds', collect([$guestA->id, $guestB->id])->map(fn ($id) => (string) $id)->all())
            ->call('executeBulkDelete')
            ->assertDispatched('toast', type: 'success');

        $this->assertNotNull($guestA->fresh());
        $this->assertNotNull($guestB->fresh());

        Queue::assertPushed(BulkPurgeAccounts::class, function (BulkPurgeAccounts $job) use ($guestA, $guestB, $admin): bool {
            return $job->userIds === [(string) $guestA->id, (string) $guestB->id]
                && $job->type === 'guest'
                && $job->requestedBy === $admin->id;
        });
    }

    public function test_bulk_force_delete_dispatches_a_queued_job_once_selection_exceeds_the_threshold(): void
    {
        Queue::fake();
        config(['panel.bulk_account_action_queue_threshold' => 1]);
        $admin = $this->actingAsSuperAdmin();

        $guestA = User::factory()->guest()->create(['banned_at' => null]);
        $guestB = User::factory()->guest()->create(['banned_at' => null]);
        $guestA->delete();
        $guestB->delete();

        Livewire::test(Index::class)
            ->set('selectedIds', collect([$guestA->id, $guestB->id])->map(fn ($id) => (string) $id)->all())
            ->call('executeBulkForceDelete')
            ->assertDispatched('toast', type: 'success');

        $this->assertNotNull(User::withTrashed()->find($guestA->id));
        $this->assertNotNull(User::withTrashed()->find($guestB->id));

        Queue::assertPushed(BulkForceDeleteAccounts::class, function (BulkForceDeleteAccounts $job) use ($guestA, $guestB, $admin): bool {
            return $job->userIds === [(string) $guestA->id, (string) $guestB->id]
                && $job->module === ActivityModule::Guest
                && $job->requestedBy === $admin->id;
        });
    }

    /**
     * The queue threshold only decides sync vs. async — this cap is what
     * actually stops an unbounded selection from being handed to anything at
     * all, queued included.
     */
    public function test_bulk_delete_rejects_a_selection_over_the_max_cap(): void
    {
        Queue::fake();
        config(['panel.bulk_account_action_max_selection' => 1]);
        $this->actingAsSuperAdmin();

        $guestA = User::factory()->guest()->create(['banned_at' => null]);
        $guestB = User::factory()->guest()->create(['banned_at' => null]);

        Livewire::test(Index::class)
            ->set('selectedIds', collect([$guestA->id, $guestB->id])->map(fn ($id) => (string) $id)->all())
            ->call('executeBulkDelete')
            ->assertDispatched('toast', type: 'error');

        $this->assertNotNull($guestA->fresh());
        $this->assertNotNull($guestB->fresh());
        Queue::assertNotPushed(BulkPurgeAccounts::class);
    }

    public function test_bulk_force_delete_rejects_a_selection_over_the_max_cap(): void
    {
        Queue::fake();
        config(['panel.bulk_account_action_max_selection' => 1]);
        $this->actingAsSuperAdmin();

        $guestA = User::factory()->guest()->create(['banned_at' => null]);
        $guestB = User::factory()->guest()->create(['banned_at' => null]);
        $guestA->delete();
        $guestB->delete();

        Livewire::test(Index::class)
            ->set('selectedIds', collect([$guestA->id, $guestB->id])->map(fn ($id) => (string) $id)->all())
            ->call('executeBulkForceDelete')
            ->assertDispatched('toast', type: 'error');

        $this->assertNotNull(User::withTrashed()->find($guestA->id));
        $this->assertNotNull(User::withTrashed()->find($guestB->id));
        Queue::assertNotPushed(BulkForceDeleteAccounts::class);
    }
}
