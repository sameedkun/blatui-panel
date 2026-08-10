<?php

namespace Tests\Feature;

use App\Livewire\Admin\Management\Users\Show;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserDeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    /** Staff granted exactly the given abilities (plus panel access and users.manage, required just to mount the profile). */
    private function staffWith(array $abilities): User
    {
        foreach (array_merge(['panel.access-admin', 'users.manage', 'users.view'], $abilities) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $staff->givePermissionTo(array_merge(['panel.access-admin', 'users.manage', 'users.view'], $abilities));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    public function test_blocking_a_device_deletes_its_token_and_writes_an_audit_entry(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $token = $user->createToken('test')->accessToken;
        $device = UserDevice::factory()->for($user)->create(['token_id' => $token->id]);

        Livewire::test(Show::class, ['user' => $user])
            ->call('openBlockDeviceDialog', $device->ulid)
            ->set('blockDeviceReason', 'Reported stolen by the account owner.')
            ->call('blockDevice')
            ->assertHasNoErrors();

        $fresh = $device->fresh();
        $this->assertNotNull($fresh->blocked_at);
        $this->assertNull($fresh->token_id);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);

        $this->assertTrue(
            Activity::where('event', 'blocked')->where('causer_id', $admin->id)->exists(),
        );
    }

    public function test_blocking_requires_a_reason_of_at_least_ten_characters(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $device = UserDevice::factory()->for($user)->create();

        Livewire::test(Show::class, ['user' => $user])
            ->call('openBlockDeviceDialog', $device->ulid)
            ->set('blockDeviceReason', 'too short')
            ->call('blockDevice')
            ->assertHasErrors(['blockDeviceReason']);

        $this->assertNull($device->fresh()->blocked_at);
    }

    public function test_unblocking_clears_the_block_but_does_not_restore_the_token(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $device = UserDevice::factory()->for($user)->blocked()->create();

        Livewire::test(Show::class, ['user' => $user])->call('unblockDevice', $device->ulid);

        $fresh = $device->fresh();
        $this->assertNull($fresh->blocked_at);
        $this->assertNull($fresh->token_id);
    }

    public function test_revoking_a_device_deletes_its_token(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $token = $user->createToken('test')->accessToken;
        $device = UserDevice::factory()->for($user)->create(['token_id' => $token->id]);

        Livewire::test(Show::class, ['user' => $user])
            ->call('confirmRevokeDevice', $device->ulid)
            ->call('revokeDevice');

        $this->assertNotNull($device->fresh()->revoked_at);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_revoke_all_revokes_every_active_device_for_this_user_only(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        UserDevice::factory()->for($user)->count(3)->create();

        $otherUser = User::factory()->app()->create();
        $otherDevice = UserDevice::factory()->for($otherUser)->create();

        Livewire::test(Show::class, ['user' => $user])
            ->call('confirmRevokeAllDevices')
            ->call('revokeAllDevices');

        $this->assertSame(3, UserDevice::where('user_id', $user->id)->whereNotNull('revoked_at')->count());
        $this->assertNull($otherDevice->fresh()->revoked_at);
    }

    /** Defense-in-depth: the profile page can never reach another account's device even by a crafted ulid. */
    public function test_the_profile_cannot_act_on_another_users_device_by_ulid(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();
        $otherUsersDevice = UserDevice::factory()->for(User::factory()->app())->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Show::class, ['user' => $user])
            ->call('confirmRevokeDevice', $otherUsersDevice->ulid);
    }

    public function test_device_actions_require_their_own_devices_permission(): void
    {
        // Has users.manage (enough to view the profile) but no devices.* abilities.
        $this->actingAs($this->staffWith([]));
        $user = User::factory()->app()->create();
        $device = UserDevice::factory()->for($user)->create();

        Livewire::test(Show::class, ['user' => $user])
            ->call('openBlockDeviceDialog', $device->ulid)->assertForbidden();

        Livewire::test(Show::class, ['user' => $user])
            ->call('confirmRevokeDevice', $device->ulid)->assertForbidden();

        Livewire::test(Show::class, ['user' => $user])
            ->call('unblockDevice', $device->ulid)->assertForbidden();
    }
}
