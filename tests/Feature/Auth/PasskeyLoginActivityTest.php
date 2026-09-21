<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Models\Passkey;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasskeyLoginActivityTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithPanelAccess(): User
    {
        $permission = Permission::firstOrCreate(['name' => config('panel.access.admin'), 'guard_name' => config('panel.guard')]);
        $role = Role::firstOrCreate(['name' => 'staff-role', 'guard_name' => config('panel.guard')]);
        $role->givePermissionTo($permission);

        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $staff->assignRole($role);

        return $staff;
    }

    public function test_a_passkey_login_is_tagged_with_a_passkey_area_in_the_activity_log(): void
    {
        $staff = $this->staffWithPanelAccess();
        Passkey::factory()->create(['authenticatable_id' => $staff->id]);

        config(['passkeys.actions.find_passkey' => FakeFindPasskeyToAuthenticateAction::class]);
        FakeFindPasskeyToAuthenticateAction::$resolvesTo = $staff;

        // Seeds the session key AuthenticateUsingPasskeyController reads — a plain
        // POST with no prior GET would 302 back as "invalid passkey" before ever
        // reaching find_passkey.
        $this->get(route('passkeys.authentication_options'));

        $response = $this->post(route('passkeys.login'), [
            'start_authentication_response' => '{}',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($staff);

        $login = Activity::where('event', 'login')->where('causer_id', $staff->id)->latest()->first();

        $this->assertNotNull($login);
        $this->assertSame('passkey', $login->properties['area'] ?? null);
    }

    public function test_a_password_login_is_not_tagged_as_a_passkey_login(): void
    {
        $staff = $this->staffWithPanelAccess();
        $staff->forceFill(['password' => bcrypt('secret-password')])->save();

        Livewire::test(Login::class)
            ->set('email', $staff->email)
            ->set('password', 'secret-password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($staff);

        $login = Activity::where('event', 'login')->where('causer_id', $staff->id)->latest()->first();

        $this->assertNotNull($login);
        $this->assertArrayNotHasKey('area', $login->properties->toArray());
    }
}

class FakeFindPasskeyToAuthenticateAction extends FindPasskeyToAuthenticateAction
{
    public static ?User $resolvesTo = null;

    public function execute(string $publicKeyCredentialJson, string $passkeyOptionsJson): ?Passkey
    {
        return self::$resolvesTo?->passkeys()->first();
    }
}
