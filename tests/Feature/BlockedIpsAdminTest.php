<?php

namespace Tests\Feature;

use App\Livewire\Admin\Management\BlockedIps\Form;
use App\Livewire\Admin\Management\BlockedIps\Index;
use App\Models\BlockedIp;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BlockedIpsAdminTest extends TestCase
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

    public function test_english_and_turkish_blocked_ip_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('blocked_ips', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('blocked_ips', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_blocked_ip_pages_use_the_request_locale_in_content_and_browser_titles(): void
    {
        $this->actingAsAdminWith([
            'panel.access-admin',
            'blocked-ips.view',
            'blocked-ips.create',
            'blocked-ips.update',
        ]);
        $blockedIp = BlockedIp::factory()->global()->create(['ip_address' => '198.51.100.77']);

        $indexResponse = $this->withCookie('locale', 'tr')->get(route('admin.blocked-ips.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('<title>'.__('blocked_ips.title').' — '.config('app.name').'</title>', false);
        $indexResponse->assertSee(__('blocked_ips.subtitle'));
        $indexResponse->assertSee(__('blocked_ips.filters.search'));

        $createResponse = $this->withCookie('locale', 'tr')->get(route('admin.blocked-ips.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('<title>'.__('blocked_ips.form.create_title').' — '.config('app.name').'</title>', false);
        $createResponse->assertSee(__('blocked_ips.form.create_description'));

        $editResponse = $this->withCookie('locale', 'tr')->get(route('admin.blocked-ips.edit', $blockedIp));
        $editResponse->assertOk();
        $editResponse->assertSee('<title>'.__('blocked_ips.form.edit_title').' — '.config('app.name').'</title>', false);
        $editResponse->assertSee(__('blocked_ips.form.edit_description'));
    }

    public function test_blocked_ip_validation_toasts_and_activity_drawer_use_the_active_locale(): void
    {
        App::setLocale('tr');
        $this->actingAsAdminWith([
            'blocked-ips.view',
            'blocked-ips.create',
            'blocked-ips.create-global',
            'blocked-ips.delete',
            'devices.view',
        ]);

        Livewire::test(Form::class)
            ->set('ipAddress', 'not-an-ip')
            ->set('scope', 'global')
            ->set('globalConfirmed', true)
            ->call('save')
            ->assertHasErrors(['ipAddress' => 'ip'])
            ->assertSee(__('blocked_ips.validation.ip_invalid'));

        Livewire::test(Form::class)
            ->set('ipAddress', '198.51.100.90')
            ->set('scope', 'global')
            ->call('save')
            ->assertHasErrors(['scope'])
            ->assertSee(__('blocked_ips.validation.confirm_global'));

        $blockedIp = BlockedIp::factory()->global()->create(['ip_address' => '198.51.100.91']);

        Livewire::test(Index::class)
            ->call('openIpActivityPanel', '198.51.100.92')
            ->assertSee(__('blocked_ips.empty.devices'))
            ->call('confirmDelete', $blockedIp->id)
            ->call('delete')
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('blocked_ips.toasts.deleted', ['ip' => $blockedIp->ip_address]),
            );
    }

    public function test_the_create_and_edit_routes_are_permission_gated(): void
    {
        $this->actingAsAdminWith(['blocked-ips.view']);
        $blockedIp = BlockedIp::factory()->global()->create();

        $this->get(route('admin.blocked-ips.create'))->assertForbidden();
        $this->get(route('admin.blocked-ips.edit', $blockedIp))->assertForbidden();
    }

    public function test_the_edit_page_prefills_the_form_from_the_existing_block(): void
    {
        $this->actingAsAdminWith(['blocked-ips.view', 'blocked-ips.update']);
        $target = User::factory()->app()->create(['email' => 'existing@example.com']);
        $blockedIp = BlockedIp::factory()->forUser($target)->create([
            'ip_address' => '198.51.100.77',
            'reason' => 'Existing reason.',
        ]);

        Livewire::test(Form::class, ['blockedIp' => $blockedIp])
            ->assertSet('ipAddress', '198.51.100.77')
            ->assertSet('scope', 'user')
            ->assertSet('formUserEmail', 'existing@example.com')
            ->assertSet('reason', 'Existing reason.');
    }

    public function test_creating_a_per_user_block_requires_a_valid_user_email(): void
    {
        $admin = $this->actingAsAdminWith(['blocked-ips.view', 'blocked-ips.create']);
        $target = User::factory()->app()->create(['email' => 'target@example.com']);

        Livewire::test(Form::class)
            ->set('ipAddress', '198.51.100.5')
            ->set('scope', 'user')
            ->set('formUserEmail', $target->email)
            ->set('reason', 'Repeated abuse from this address.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.blocked-ips.index'));

        $this->assertDatabaseHas('blocked_ips', [
            'ip_address' => '198.51.100.5',
            'user_id' => $target->id,
        ]);

        $this->assertTrue(Activity::where('event', 'created')->where('causer_id', $admin->id)->exists());
    }

    public function test_user_search_and_select_filters_app_users_and_populates_form_user_email(): void
    {
        $this->actingAsAdminWith(['blocked-ips.view', 'blocked-ips.create']);

        $appUser1 = User::factory()->app()->create(['name' => 'Alice Smith', 'email' => 'alice@example.com']);
        $appUser2 = User::factory()->app()->create(['name' => 'Bob Jones', 'email' => 'bob@example.com']);
        $staffUser = User::factory()->create(['type' => 'staff', 'name' => 'Alice Admin', 'email' => 'alice.admin@example.com']);

        Livewire::test(Form::class)
            ->set('userSearch', 'Alice')
            ->assertSee('Alice Smith')
            ->assertSee('alice@example.com')
            ->assertDontSee('Alice Admin') // Staff user should not be in appUsers scope
            ->call('selectUser', $appUser1->email)
            ->assertSet('formUserEmail', 'alice@example.com')
            ->assertSet('userSearch', '')
            ->call('clearUser')
            ->assertSet('formUserEmail', '');
    }

    public function test_editing_a_block_updates_its_reason_and_expiry(): void
    {
        $admin = $this->actingAsAdminWith(['blocked-ips.view', 'blocked-ips.update']);
        $target = User::factory()->app()->create();
        $blockedIp = BlockedIp::factory()->forUser($target)->create(['reason' => 'Old reason.']);

        Livewire::test(Form::class, ['blockedIp' => $blockedIp])
            ->set('reason', 'Updated reason.')
            ->set('permanent', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.blocked-ips.index'));

        $blockedIp->refresh();
        $this->assertSame('Updated reason.', $blockedIp->reason);
        $this->assertNull($blockedIp->expires_at);
        $this->assertTrue(Activity::where('event', 'updated')->where('causer_id', $admin->id)->exists());
    }

    public function test_creating_a_global_block_is_rejected_without_blocked_ips_create_global_permission(): void
    {
        $this->actingAsAdminWith(['blocked-ips.view', 'blocked-ips.create']);

        Livewire::test(Form::class)
            ->set('ipAddress', '198.51.100.9')
            ->set('scope', 'global')
            ->set('globalConfirmed', true)
            ->call('save');

        $this->assertDatabaseMissing('blocked_ips', ['ip_address' => '198.51.100.9']);
    }

    public function test_a_global_block_cannot_be_saved_without_the_second_confirmation(): void
    {
        $this->actingAsAdminWith(['blocked-ips.view', 'blocked-ips.create', 'blocked-ips.create-global']);

        Livewire::test(Form::class)
            ->set('ipAddress', '198.51.100.9')
            ->set('scope', 'global')
            // globalConfirmed left false
            ->call('save')
            ->assertHasErrors(['scope']);

        $this->assertDatabaseMissing('blocked_ips', ['ip_address' => '198.51.100.9']);
    }

    public function test_a_confirmed_global_block_saves_successfully(): void
    {
        $admin = $this->actingAsAdminWith(['blocked-ips.view', 'blocked-ips.create', 'blocked-ips.create-global']);

        Livewire::test(Form::class)
            ->set('ipAddress', '198.51.100.9')
            ->set('scope', 'global')
            ->set('globalConfirmed', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.blocked-ips.index'));

        $this->assertDatabaseHas('blocked_ips', ['ip_address' => '198.51.100.9', 'user_id' => null]);
        $this->assertTrue(Activity::where('event', 'created')->where('causer_id', $admin->id)->exists());
    }

    public function test_duplicate_global_block_shows_a_field_error_instead_of_a_500(): void
    {
        $this->actingAsAdminWith(['blocked-ips.view', 'blocked-ips.create', 'blocked-ips.create-global']);
        BlockedIp::factory()->global()->create(['ip_address' => '198.51.100.20']);

        Livewire::test(Form::class)
            ->set('ipAddress', '198.51.100.20')
            ->set('scope', 'global')
            ->set('globalConfirmed', true)
            ->call('save')
            ->assertHasErrors(['ipAddress']);

        $this->assertSame(1, BlockedIp::where('ip_address', '198.51.100.20')->count());
    }

    public function test_deleting_a_block_removes_it_and_logs_an_audit_entry(): void
    {
        $admin = $this->actingAsAdminWith(['blocked-ips.view', 'blocked-ips.delete']);
        $blockedIp = BlockedIp::factory()->global()->create();

        Livewire::test(Index::class)
            ->call('confirmDelete', $blockedIp->id)
            ->call('delete');

        $this->assertDatabaseMissing('blocked_ips', ['id' => $blockedIp->id]);
        $this->assertTrue(Activity::where('event', 'deleted')->where('causer_id', $admin->id)->exists());
    }

    public function test_delete_all_expired_only_removes_expired_blocks(): void
    {
        $this->actingAsAdminWith(['blocked-ips.view', 'blocked-ips.delete']);
        $expired = BlockedIp::factory()->global()->expired()->create();
        $active = BlockedIp::factory()->global()->create();

        Livewire::test(Index::class)
            ->call('confirmDeleteAllExpired')
            ->call('deleteAllExpired');

        $this->assertDatabaseMissing('blocked_ips', ['id' => $expired->id]);
        $this->assertDatabaseHas('blocked_ips', ['id' => $active->id]);
    }

    public function test_ip_activity_shows_a_user_without_a_profile_link_when_the_admin_cannot_manage_users(): void
    {
        $admin = $this->actingAsAdminWith(['blocked-ips.view', 'devices.view']);
        $user = User::factory()->app()->create();
        $device = UserDevice::factory()->for($user)->create(['ip_address' => '198.51.100.42']);

        Livewire::test(Index::class)
            ->call('openIpActivityPanel', $device->ip_address)
            ->assertSee($user->name)
            ->assertSee($user->email)
            ->assertDontSeeHtml('href="'.route('admin.users.show', $user).'"');
    }
}
