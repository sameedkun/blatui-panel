<?php

namespace Tests\Feature\Admin\Languages;

use App\Livewire\Admin\Application\Language\Form;
use App\Livewire\Admin\Application\Language\Index;
use App\Models\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LanguagesAdminTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdminWith(array $permissions): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);

        $role = Role::firstOrCreate(['name' => 'test-role-'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $admin->assignRole($role);

        $this->actingAs($admin);

        return $admin;
    }

    public function test_english_and_turkish_language_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('languages', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('languages', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_language_pages_use_the_request_locale_in_content_and_browser_titles(): void
    {
        $this->actingAsAdminWith([
            'panel.access-admin',
            'languages.view',
            'languages.create',
            'languages.edit',
        ]);
        $language = Language::factory()->create(['name' => 'English', 'code' => 'en']);

        $indexResponse = $this->withCookie('locale', 'tr')->get(route('admin.languages.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('<title>'.__('languages.title').' — '.config('app.name').'</title>', false);
        $indexResponse->assertSee(__('languages.subtitle'));
        $indexResponse->assertSee(__('languages.filters.search'));

        $createResponse = $this->withCookie('locale', 'tr')->get(route('admin.languages.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('<title>'.__('languages.form.create_title').' — '.config('app.name').'</title>', false);
        $createResponse->assertSee(__('languages.form.create_description'));

        $editResponse = $this->withCookie('locale', 'tr')->get(route('admin.languages.edit', $language));
        $editResponse->assertOk();
        $editResponse->assertSee('<title>'.__('languages.form.edit_title').' — '.config('app.name').'</title>', false);
        $editResponse->assertSee(__('languages.form.edit_description'));
    }

    public function test_language_validation_success_messages_dialogs_and_delete_toasts_use_the_active_locale(): void
    {
        App::setLocale('tr');
        $this->actingAsAdminWith([
            'languages.view',
            'languages.create',
            'languages.delete',
        ]);

        Livewire::test(Form::class)
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'code' => 'required'])
            ->assertSee(__('languages.validation.name_required'))
            ->assertSee(__('languages.validation.code_required'));

        Livewire::test(Form::class)
            ->set('name', 'Türkçe')
            ->set('native_name', 'Türkçe')
            ->set('code', 'tr')
            ->set('flag', 'tr')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.languages.index'));

        $this->assertSame(
            __('languages.toasts.created', ['name' => 'Türkçe']),
            session('toast.title'),
        );

        $language = Language::where('code', 'tr')->firstOrFail();

        Livewire::test(Index::class)
            ->assertSee(__('languages.dialogs.delete_title'))
            ->assertSee(__('languages.dialogs.delete_description'))
            ->call('confirmDelete', $language->id)
            ->call('delete')
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('languages.toasts.deleted', ['name' => 'Türkçe']),
            );

        $this->assertModelMissing($language);

        $bulkLanguages = Language::factory()->count(2)->create();

        Livewire::test(Index::class)
            ->set('selectedIds', $bulkLanguages->pluck('id')->map(fn (int $id): string => (string) $id)->all())
            ->call('executeBulkDelete')
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('languages.toasts.bulk_deleted', ['count' => 2]),
            );

        $bulkLanguages->each(fn (Language $bulkLanguage) => $this->assertModelMissing($bulkLanguage));
    }
}
