<?php

namespace Tests\Feature;

use App\Livewire\Admin\Management\Users\Index as UsersIndex;
use App\Livewire\Admin\Management\Users\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        foreach (['panel.access-admin', 'users.view', 'users.manage', 'users.edit', 'users.ban', 'users.unban', 'users.delete', 'users.restore', 'users.force-delete'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $staff->givePermissionTo(array_merge(['panel.access-admin'], $abilities));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    public function test_page_loads_for_a_permitted_user(): void
    {
        $this->actingAs($this->staffWith(['users.view', 'users.manage']));
        $user = User::factory()->app()->create(['name' => 'Ada Lovelace']);

        $this->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSeeLivewire(Show::class)
            ->assertSee('Ada Lovelace')
            // Title/breadcrumb read "Users → {name}" — never the component name "Show".
            ->assertSeeText('Ada Lovelace')
            ->assertDontSeeText('Users → Show');
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

    public function test_ban_from_profile_updates_the_header_state(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();

        Livewire::test(Show::class, ['user' => $user])
            ->assertDontSee('Unban')
            ->call('openBanDialog', $user->id)
            ->set('banReason', 'spam')
            ->call('confirmBan')
            ->assertSee('Unban'); // refreshRecord() reflects the new banned state
    }

    public function test_tab_is_url_addressable_and_survives_refresh(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();

        Livewire::withQueryParams(['tab' => 'devices'])
            ->test(Show::class, ['user' => $user])
            ->assertSet('tab', 'devices')
            ->assertSee('Devices')
            ->assertSee('Coming soon');
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
        $this->assertNull($stats['Activity']['value']);
        $this->assertSame('Mar 15, 2024', $stats['Joined']['value']);
    }

    public function test_overview_shows_password_changed_at(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create([
            'password_changed_at' => now()->parse('2025-01-10 12:00'),
        ]);

        Livewire::test(Show::class, ['user' => $user])
            ->assertSee('Password changed')
            ->assertSee('Jan 10, 2025');
    }

    public function test_trashed_user_profile_is_reachable_via_the_route(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create(['name' => 'Deleted Dana']);
        $user->delete();

        $this->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee('Deleted Dana');
    }

    public function test_header_shows_only_restore_and_force_delete_when_trashed(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $user->delete();
        $user->refresh();

        // Text-only assertSee/assertDontSee would false-positive on the always-rendered
        // (Alpine-hidden) dialog markup shared across states, so assert on the
        // click hooks that are unique to each menu item instead. Restore/Force Delete
        // are secondary actions (Alpine @click="$wire.X()" inside the Actions menu).
        Livewire::test(Show::class, ['user' => $user])
            ->assertSee('$wire.confirmRestore('.$user->id.')', false)
            ->assertSee('$wire.confirmForceDelete('.$user->id.')', false)
            ->assertDontSee('$wire.openScheduleDeletionDialog('.$user->id.')', false)
            ->assertDontSee('$wire.confirmDelete('.$user->id.')', false)
            ->assertDontSee('$wire.confirmInstantPurge('.$user->id.')', false)
            ->assertDontSee('$wire.openBanDialog('.$user->id.')', false)
            ->assertDontSee('wire:click="openBanDialog('.$user->id.')"', false)
            ->assertDontSeeHtml('href="'.route('admin.users.edit', $user).'"');
    }

    public function test_header_keeps_ban_available_and_shows_purge_actions_when_pending(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->pendingDeletion('admin')->create();

        // Ban/Unban are primary (desktop: real wire:click; mobile menu: Alpine @click).
        Livewire::test(Show::class, ['user' => $user])
            ->assertSee('$wire.stopDeletion('.$user->id.')', false)
            ->assertSee('$wire.confirmInstantPurge('.$user->id.')', false)
            ->assertSee('wire:click="openBanDialog('.$user->id.')"', false) // grace-window abuse still bannable
            ->assertDontSee('$wire.openScheduleDeletionDialog('.$user->id.')', false)
            ->assertDontSee('$wire.confirmDelete('.$user->id.')', false)
            ->assertDontSeeHtml('href="'.route('admin.users.edit', $user).'"');
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

    public function test_scaffolded_credential_actions_toast_not_yet_available_instead_of_silently_doing_nothing(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create(['email_verified_at' => null]);

        Livewire::test(Show::class, ['user' => $user])
            ->call('verifyEmailManually')
            ->assertDispatched('toast', type: 'info')
            ->call('resendVerificationEmail')
            ->assertDispatched('toast', type: 'info')
            ->call('sendPasswordResetLink')
            ->assertDispatched('toast', type: 'info');
    }
}
