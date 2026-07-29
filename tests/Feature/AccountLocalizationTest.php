<?php

namespace Tests\Feature;

use App\Livewire\Admin\Account\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsStaffWithAccountAccess(): User
    {
        $permission = Permission::firstOrCreate([
            'name' => 'settings.mail.edit',
            'guard_name' => config('panel.guard'),
        ]);
        $role = Role::firstOrCreate([
            'name' => config('panel.super_admin_role'),
            'guard_name' => config('panel.guard'),
        ]);
        $role->givePermissionTo($permission);

        $staff = User::factory()->create([
            'type' => 'staff',
            'banned_at' => null,
            'email_verified_at' => now(),
            'password_changed_at' => null,
        ]);
        $staff->assignRole($role);

        $this->actingAs($staff);

        return $staff;
    }

    public function test_english_and_turkish_account_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('account', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('account', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_account_page_title_sections_roles_and_permissions_use_the_request_locale(): void
    {
        $staff = $this->actingAsStaffWithAccountAccess();

        activity()
            ->causedBy($staff)
            ->performedOn($staff)
            ->withProperties(['module' => 'staff'])
            ->event('updated')
            ->log('staff updated');

        $response = $this->withCookie('locale', 'tr')->get(route('admin.account'));

        $response->assertOk();
        $response->assertSee('<title>'.__('account.title').' — '.config('app.name').'</title>', false);
        $response->assertSee(__('account.subtitle'));
        $response->assertSee(__('account.tabs.profile'));
        $response->assertSee(__('account.overview.account_details'));
        $response->assertSee(__('account.security.logout_description'));
        $response->assertSee(__('account.activity.description'));
        $response->assertSee(__('roles.role_labels.super_admin'));
        $response->assertSee(__('navigation.modules.settings'));
        $response->assertSee(__('roles.permissions.scoped', [
            'scope' => __('roles.permissions.scopes.mail'),
            'action' => __('roles.permissions.actions.edit'),
        ]));
        $response->assertSee(__('activity_logs.enums.actions.updated'));
        $response->assertSee(__('activity_logs.enums.modules.staff'));
    }

    public function test_account_validation_and_profile_and_password_toasts_use_the_active_locale(): void
    {
        App::setLocale('tr');
        $staff = $this->actingAsStaffWithAccountAccess();

        Livewire::test(Index::class)
            ->set('name', '')
            ->set('email', 'invalid')
            ->call('saveProfile')
            ->assertHasErrors([
                'name' => 'required',
                'email' => 'email',
            ])
            ->assertSee(__('account.validation.name_required'))
            ->assertSee(__('account.validation.email_invalid'));

        Livewire::test(Index::class)
            ->set('name', 'Yerelleştirilmiş Personel')
            ->call('saveProfile')
            ->assertHasNoErrors()
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('account.toasts.profile_updated'),
                description: null,
            );

        Livewire::test(Index::class)
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword')
            ->assertHasErrors(['current_password' => 'current_password'])
            ->assertSee(__('account.validation.current_password_incorrect'));

        Livewire::test(Index::class)
            ->set('current_password', 'password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('account.toasts.password_updated'),
                description: null,
            );

        $this->assertSame('Yerelleştirilmiş Personel', $staff->fresh()->name);
    }

    public function test_logout_other_devices_validation_and_toast_use_the_active_locale(): void
    {
        App::setLocale('tr');
        $this->actingAsStaffWithAccountAccess();

        Livewire::test(Index::class)
            ->set('logout_password', 'wrong-password')
            ->call('logoutOtherDevices')
            ->assertHasErrors(['logout_password' => 'current_password'])
            ->assertSee(__('account.validation.logout_password_incorrect'));

        Livewire::test(Index::class)
            ->set('logout_password', 'password')
            ->call('logoutOtherDevices')
            ->assertHasNoErrors()
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('account.toasts.other_sessions_logged_out'),
                description: null,
            );
    }
}
