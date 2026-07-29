<?php

namespace Tests\Feature;

use App\Livewire\Admin\Administration\Roles\Form;
use App\Livewire\Admin\Administration\Roles\Index;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(config('panel.super_admin_role'));
        $this->actingAs($admin);

        return $admin;
    }

    public function test_english_and_turkish_role_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('roles', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('roles', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_role_pages_titles_matrix_and_dialogs_use_the_request_locale(): void
    {
        $this->actingAsSuperAdmin();
        $customRole = Role::create(['name' => 'billing-support', 'guard_name' => config('panel.guard')]);
        $protectedRole = Role::findByName(config('panel.super_admin_role'), config('panel.guard'));

        $index = $this->withCookie('locale', 'tr')->get(route('admin.roles.index'));
        $index->assertOk();
        $index->assertSee('<title>'.__('roles.title').' — '.config('app.name').'</title>', false);
        $index->assertSee(__('roles.subtitle'));
        $index->assertSee(__('roles.dialogs.delete_title'));

        $create = $this->withCookie('locale', 'tr')->get(route('admin.roles.create'));
        $create->assertOk();
        $create->assertSee('<title>'.__('roles.form.create_title').' — '.config('app.name').'</title>', false);
        $create->assertSee(__('roles.form.create_description'));
        $create->assertSee(__('roles.form.panel_access'));
        $create->assertSee(__('navigation.groups.management'));
        $create->assertSee(__('navigation.modules.users'));
        $create->assertSee(__('roles.permissions.actions.force-delete'));
        $create->assertSee(__('roles.permissions.scoped', [
            'scope' => __('roles.permissions.scopes.mail'),
            'action' => __('roles.permissions.actions.edit'),
        ]));

        $edit = $this->withCookie('locale', 'tr')->get(route('admin.roles.edit', $customRole));
        $edit->assertOk();
        $edit->assertSee('<title>'.__('roles.form.edit_title').' — '.config('app.name').'</title>', false);

        $view = $this->withCookie('locale', 'tr')->get(route('admin.roles.edit', $protectedRole));
        $view->assertOk();
        $view->assertSee('<title>'.__('roles.form.view_title').' — '.config('app.name').'</title>', false);
        $view->assertSee(__('roles.form.protected_title'));
        $view->assertSee(__('roles.form.protected_description', [
            'name' => __('roles.role_labels.super_admin'),
        ]));
    }

    public function test_role_validation_and_create_toast_use_the_active_locale(): void
    {
        App::setLocale('tr');
        $this->actingAsSuperAdmin();

        Livewire::test(Form::class)
            ->set('name', 'Invalid Role')
            ->call('save')
            ->assertHasErrors(['name' => 'regex'])
            ->assertSee(__('roles.validation.name_format'));

        Livewire::test(Form::class)
            ->set('name', 'teknik-destek')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.roles.index'));

        $this->assertSame(
            __('roles.toasts.created', ['name' => 'Teknik Destek']),
            session('toast.title'),
        );
    }

    public function test_role_delete_dialog_and_toast_use_the_active_locale(): void
    {
        App::setLocale('tr');
        $this->actingAsSuperAdmin();

        $role = Role::create(['name' => 'temporary-role', 'guard_name' => config('panel.guard')]);
        $staff = User::factory()->create(['type' => 'staff']);
        $staff->assignRole($role);

        Livewire::test(Index::class)
            ->call('confirmDelete', $role->id)
            ->assertSet('deletingStaffCount', 1)
            ->assertSee(trans_choice('roles.dialogs.staff_affected', 1, ['count' => 1]))
            ->call('delete')
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('roles.toasts.deleted', ['name' => 'Temporary Role']),
            );

        $this->assertNull(Role::find($role->id));
    }
}
