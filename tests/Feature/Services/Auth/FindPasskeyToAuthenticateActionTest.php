<?php

namespace Tests\Feature\Services\Auth;

use App\Models\User;
use App\Services\Auth\FindPasskeyToAuthenticateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPasskeys\Models\Passkey;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FindPasskeyToAuthenticateActionTest extends TestCase
{
    use RefreshDatabase;

    private function passkeyFor(User $user): Passkey
    {
        return Passkey::factory()->create(['authenticatable_id' => $user->id]);
    }

    public function test_staff_with_panel_access_and_not_banned_may_use_their_passkey(): void
    {
        $permission = Permission::firstOrCreate(['name' => config('panel.access.admin'), 'guard_name' => config('panel.guard')]);
        $role = Role::firstOrCreate(['name' => 'staff-role', 'guard_name' => config('panel.guard')]);
        $role->givePermissionTo($permission);

        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $staff->assignRole($role);

        $action = new FindPasskeyToAuthenticateAction;

        $this->assertTrue($action->authenticatableMayUsePasskey($this->passkeyFor($staff)));
    }

    public function test_app_user_may_not_use_a_passkey_even_if_one_somehow_exists(): void
    {
        $appUser = User::factory()->create(['type' => 'app']);

        $action = new FindPasskeyToAuthenticateAction;

        $this->assertFalse($action->authenticatableMayUsePasskey($this->passkeyFor($appUser)));
    }

    public function test_guest_may_not_use_a_passkey(): void
    {
        $guest = User::factory()->create(['type' => 'guest']);

        $action = new FindPasskeyToAuthenticateAction;

        $this->assertFalse($action->authenticatableMayUsePasskey($this->passkeyFor($guest)));
    }

    public function test_banned_staff_may_not_use_their_passkey(): void
    {
        $permission = Permission::firstOrCreate(['name' => config('panel.access.admin'), 'guard_name' => config('panel.guard')]);
        $role = Role::firstOrCreate(['name' => 'staff-role', 'guard_name' => config('panel.guard')]);
        $role->givePermissionTo($permission);

        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => now()]);
        $staff->assignRole($role);

        $action = new FindPasskeyToAuthenticateAction;

        $this->assertFalse($action->authenticatableMayUsePasskey($this->passkeyFor($staff)));
    }

    public function test_staff_without_panel_access_permission_may_not_use_their_passkey(): void
    {
        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);

        $action = new FindPasskeyToAuthenticateAction;

        $this->assertFalse($action->authenticatableMayUsePasskey($this->passkeyFor($staff)));
    }
}
