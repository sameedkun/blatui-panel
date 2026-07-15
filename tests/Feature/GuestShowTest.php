<?php

namespace Tests\Feature;

use App\Livewire\Admin\Management\Guests\Index as GuestsIndex;
use App\Livewire\Admin\Management\Guests\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GuestShowTest extends TestCase
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
        foreach (['panel.access-admin', 'guests.view', 'guests.manage', 'guests.ban', 'guests.unban', 'guests.delete', 'guests.restore', 'guests.force-delete', 'guests.convert'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $staff->givePermissionTo(array_merge(['panel.access-admin'], $abilities));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    public function test_page_loads_for_a_permitted_guest(): void
    {
        $this->actingAs($this->staffWith(['guests.view', 'guests.manage']));
        $guest = User::factory()->guest()->create(['name' => 'Grace Hopper', 'banned_at' => null]);

        $this->get(route('admin.guests.show', $guest))
            ->assertOk()
            ->assertSeeLivewire(Show::class)
            ->assertSee('Grace Hopper')
            ->assertDontSeeText('Guests → Show');
    }

    public function test_page_403s_without_guests_manage_permission(): void
    {
        $this->actingAs($this->staffWith(['guests.view'])); // no guests.manage
        $guest = User::factory()->guest()->create();

        $this->get(route('admin.guests.show', $guest))->assertForbidden();
    }

    public function test_page_404s_for_a_non_guest_user(): void
    {
        $this->actingAsSuperAdmin();
        $appUser = User::factory()->app()->create();

        $this->get(route('admin.guests.show', $appUser))->assertNotFound();
    }

    public function test_header_actions_respect_their_own_permission(): void
    {
        // Has view/manage but NOT ban / delete.
        $this->actingAs($this->staffWith(['guests.view', 'guests.manage']));
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        Livewire::test(Show::class, ['user' => $guest])
            ->call('openBanDialog', $guest->id)->assertForbidden();

        Livewire::test(Show::class, ['user' => $guest])
            ->call('confirmDelete', $guest->id)->assertForbidden();
    }

    public function test_header_ban_matches_an_index_ban_on_every_axis(): void
    {
        $this->actingAsSuperAdmin();
        $fromIndex = User::factory()->guest()->create(['banned_at' => null]);
        $fromProfile = User::factory()->guest()->create(['banned_at' => null]);

        Livewire::test(GuestsIndex::class)
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
        $this->assertSame('guest', $profileRow->properties['module']);
        $this->assertSame('banned', $profileRow->event);
    }

    public function test_ban_from_profile_updates_the_header_state(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        Livewire::test(Show::class, ['user' => $guest])
            ->assertDontSee('Unban')
            ->call('openBanDialog', $guest->id)
            ->set('banReason', 'spam')
            ->call('confirmBan')
            ->assertSee('Unban'); // refreshRecord() reflects the new banned state
    }

    public function test_tab_is_url_addressable_and_survives_refresh(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        Livewire::withQueryParams(['tab' => 'activity'])
            ->test(Show::class, ['user' => $guest])
            ->assertSet('tab', 'activity')
            ->assertSee('Activity')
            ->assertSee('Coming soon');
    }

    public function test_stats_show_real_joined_date_and_placeholders_never_fake_zeros(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create([
            'banned_at' => null,
            'registration_date' => now()->parse('2024-03-15'),
        ]);

        $component = Livewire::test(Show::class, ['user' => $guest]);

        $component->assertSee('Mar 15, 2024');
        $component->assertSee('Coming soon');

        $stats = collect($component->instance()->statCards())->keyBy('label');
        $this->assertCount(3, $stats);
        $this->assertNull($stats['Subscriptions']['value']);
        $this->assertNull($stats['Activity']['value']);
        $this->assertSame('Mar 15, 2024', $stats['Joined']['value']);
    }

    public function test_trashed_guest_profile_is_reachable_via_the_route(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create(['name' => 'Deleted Dana', 'banned_at' => null]);
        $guest->delete();

        $this->get(route('admin.guests.show', $guest))
            ->assertOk()
            ->assertSee('Deleted Dana');
    }

    public function test_header_shows_only_restore_and_force_delete_when_trashed(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create(['banned_at' => null]);
        $guest->delete();
        $guest->refresh();

        // Text-only assertSee/assertDontSee would false-positive on the always-rendered
        // (Alpine-hidden) dialog markup shared across states, so assert on the
        // click hooks that are unique to each menu item instead.
        Livewire::test(Show::class, ['user' => $guest])
            ->assertSee('$wire.confirmRestore('.$guest->id.')', false)
            ->assertSee('$wire.confirmForceDelete('.$guest->id.')', false)
            ->assertDontSee('$wire.confirmDelete('.$guest->id.')', false)
            ->assertDontSee('$wire.openConvertDialog('.$guest->id.')', false)
            ->assertDontSee('$wire.openBanDialog('.$guest->id.')', false)
            ->assertDontSee('wire:click="openBanDialog('.$guest->id.')"', false);
    }

    public function test_delete_from_profile_permanently_purges_and_redirects_to_index(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        Livewire::test(Show::class, ['user' => $guest])
            ->set('deletingId', $guest->id)
            ->call('delete')
            ->assertRedirect(route('admin.guests.index'));

        $this->assertDatabaseMissing('users', ['id' => $guest->id]);

        $row = Activity::where('event', 'purged')->latest('id')->firstOrFail();
        $this->assertSame('guest', $row->properties['module']);
        $this->assertSame($guest->id, $row->properties['snapshot']['id']);
    }

    public function test_ban_action_rejects_a_trashed_account_even_if_forged(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create(['banned_at' => null]);
        $guest->delete();
        $guest->refresh();

        Livewire::test(Show::class, ['user' => $guest])
            ->call('openBanDialog', $guest->id)
            ->assertForbidden();
    }

    public function test_force_delete_from_profile_redirects_to_index(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create(['banned_at' => null]);
        $guest->delete();
        $guest->refresh();

        Livewire::test(Show::class, ['user' => $guest])
            ->set('forceDeleteId', $guest->id)
            ->call('forceDelete')
            ->assertRedirect(route('admin.guests.index'));

        $this->assertDatabaseMissing('users', ['id' => $guest->id]);
    }

    public function test_convert_to_app_user_from_profile_flips_type_and_redirects_to_users_show(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        Livewire::test(Show::class, ['user' => $guest])
            ->call('openConvertDialog', $guest->id)
            ->set('convertEmail', 'converted@example.com')
            ->set('convertName', 'Converted Name')
            ->call('confirmConvert')
            ->assertRedirect(route('admin.users.show', $guest));

        $fresh = $guest->fresh();
        $this->assertTrue($fresh->isAppUser());
        $this->assertSame('converted@example.com', $fresh->email);
        $this->assertSame('Converted Name', $fresh->name);

        $row = Activity::where('subject_id', $guest->id)->where('event', 'converted')->firstOrFail();
        $this->assertSame('admin', $row->properties['initiated_by']);
    }

    public function test_convert_action_is_forbidden_without_permission(): void
    {
        $this->actingAs($this->staffWith(['guests.view', 'guests.manage'])); // no guests.convert
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        Livewire::test(Show::class, ['user' => $guest])
            ->call('openConvertDialog', $guest->id)
            ->assertForbidden();
    }

    public function test_convert_action_hidden_for_a_banned_guest(): void
    {
        $this->actingAsSuperAdmin();
        $guest = User::factory()->guest()->create(['banned_at' => now()]);

        Livewire::test(Show::class, ['user' => $guest])
            ->assertDontSee('$wire.openConvertDialog('.$guest->id.')', false);
    }
}
