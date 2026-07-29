<?php

namespace Tests\Feature;

use App\Livewire\Admin\Administration\Staff\Form;
use App\Livewire\Admin\Administration\Staff\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate([
            'name' => config('panel.super_admin_role'),
            'guard_name' => config('panel.guard'),
        ]));

        $this->actingAs($admin);

        return $admin;
    }

    public function test_english_and_turkish_staff_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('staff', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('staff', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_staff_pages_titles_forms_and_dialogs_use_the_request_locale(): void
    {
        $this->actingAsSuperAdmin();
        $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => config('panel.guard')]);
        $staff = User::factory()->create(['type' => 'staff', 'name' => 'Destek Görevlisi']);
        $staff->assignRole($role);

        $index = $this->withCookie('locale', 'tr')->get(route('admin.staff.index'));
        $index->assertOk();
        $index->assertSee('<title>'.__('staff.title').' — '.config('app.name').'</title>', false);
        $index->assertSee(__('staff.subtitle'));
        $index->assertSee(__('staff.dialogs.ban_title'));
        $index->assertSee(__('staff.dialogs.force_delete_description'));

        $create = $this->withCookie('locale', 'tr')->get(route('admin.staff.create'));
        $create->assertOk();
        $create->assertSee('<title>'.__('staff.form.create_title').' — '.config('app.name').'</title>', false);
        $create->assertSee(__('staff.form.create_description'));
        $create->assertSee(__('staff.form.permissions_title'));

        $edit = $this->withCookie('locale', 'tr')->get(route('admin.staff.edit', $staff));
        $edit->assertOk();
        $edit->assertSee('<title>'.__('staff.form.edit_title').' — '.config('app.name').'</title>', false);
        $edit->assertSee(__('staff.form.edit_description'));
        $edit->assertSee(__('staff.form.force_password_reset'));
    }

    public function test_staff_validation_role_permission_preview_and_success_toast_use_the_active_locale(): void
    {
        App::setLocale('tr');
        Notification::fake();
        $this->actingAsSuperAdmin();

        $permission = Permission::firstOrCreate([
            'name' => 'settings.mail.edit',
            'guard_name' => config('panel.guard'),
        ]);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => config('panel.guard')]);
        $role->givePermissionTo($permission);

        Livewire::test(Form::class)
            ->call('save')
            ->assertHasErrors([
                'name' => 'required',
                'email' => 'required',
                'password' => 'required',
                'roles' => 'required',
            ])
            ->assertSee(__('staff.validation.name_required'))
            ->assertSee(__('staff.validation.email_required'))
            ->assertSee(__('staff.validation.password_required'))
            ->assertSee(__('staff.validation.roles_required'));

        Livewire::test(Form::class)
            ->set('roles', [$role->name])
            ->assertSee(__('staff.role_labels.admin'))
            ->assertSee(__('navigation.modules.settings'))
            ->assertSee(__('staff.permissions.scoped', [
                'scope' => __('staff.permissions.scopes.mail'),
                'action' => __('staff.permissions.actions.edit'),
            ]))
            ->set('name', 'Yeni Personel')
            ->set('email', 'yeni-personel@example.com')
            ->set('password', 'strong-password')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.staff.index'));

        $this->assertSame(
            __('staff.toasts.created', ['name' => 'Yeni Personel']),
            session('toast.title'),
        );
    }

    public function test_staff_ban_actions_use_localized_default_reason_and_toasts(): void
    {
        App::setLocale('tr');
        $this->actingAsSuperAdmin();
        $staff = User::factory()->create(['type' => 'staff', 'name' => 'Yönetilen Personel']);

        Livewire::test(Index::class)
            ->call('openBanDialog', $staff->id)
            ->call('confirmBan')
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('staff.toasts.banned', ['name' => $staff->name]),
            )
            ->call('unban', $staff->id)
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('staff.toasts.unbanned', ['name' => $staff->name]),
            );

        $this->assertNull($staff->fresh()->ban_reason);

        Livewire::test(Index::class)
            ->call('openBanDialog', $staff->id)
            ->call('confirmBan');

        $this->assertSame(__('staff.defaults.ban_reason'), $staff->fresh()->ban_reason);
    }
}
