<?php

namespace Tests\Feature;

use App\Livewire\Admin\Management\Guests\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
