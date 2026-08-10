<?php

namespace Tests\Feature;

use App\Enum\DeviceType;
use App\Livewire\Admin\Management\Devices\Index;
use App\Livewire\Admin\Management\Devices\SharedFingerprints;
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

class DevicesAdminTest extends TestCase
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

    public function test_english_and_turkish_device_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('devices', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('devices', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_device_pages_use_the_request_locale_in_content_and_browser_titles(): void
    {
        $this->actingAsAdminWith(['panel.access-admin', 'devices.view', 'devices.investigate']);

        $indexResponse = $this->withCookie('locale', 'tr')->get(route('admin.devices.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('<title>'.__('devices.title').' — '.config('app.name').'</title>', false);
        $indexResponse->assertSee(__('devices.subtitle'));
        $indexResponse->assertSee('placeholder="'.__('common.search').'"', false);
        $indexResponse->assertSee('<span class="sr-only">'.__('common.close').'</span>', false);

        $sharedResponse = $this->withCookie('locale', 'tr')->get(route('admin.devices.shared-fingerprints'));
        $sharedResponse->assertOk();
        $sharedResponse->assertSee('<title>'.__('devices.shared.title').' — '.config('app.name').'</title>', false);
        $sharedResponse->assertSee(__('devices.shared.description'));

        Livewire::test(SharedFingerprints::class)->assertSee(__('devices.shared.none_found'));
    }

    public function test_device_validation_toast_type_and_fallback_name_use_the_active_locale(): void
    {
        App::setLocale('tr');
        $this->actingAsAdminWith(['devices.view', 'devices.block']);
        $device = UserDevice::factory()->for(User::factory()->app())->create([
            'name' => null,
            'model' => null,
            'device_type' => DeviceType::Mobile,
        ]);

        Livewire::test(Index::class)
            ->call('openBlockDialog', $device->ulid)
            ->set('blockReason', 'kısa')
            ->call('block')
            ->assertHasErrors(['blockReason' => 'min'])
            ->set('blockReason', 'Hesap sahibi cihazın çalındığını bildirdi.')
            ->call('block')
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('devices.toasts.blocked', ['name' => __('devices.status.unnamed_device')]),
            );

        $this->assertSame(__('enums.device_type.Mobile'), DeviceType::Mobile->label());
        $this->assertSame(__('devices.status.unnamed_device'), $device->displayName());
    }

    public function test_mounting_the_index_without_view_permission_is_forbidden(): void
    {
        $this->actingAsAdminWith([]);

        Livewire::test(Index::class)->assertForbidden();
    }

    public function test_blocking_a_device_requires_permission(): void
    {
        $this->actingAsAdminWith(['devices.view']);
        $device = UserDevice::factory()->for(User::factory()->app())->create();

        Livewire::test(Index::class)
            ->call('openBlockDialog', $device->ulid)
            ->assertForbidden();
    }

    public function test_blocking_a_device_deletes_its_token_and_writes_an_audit_entry(): void
    {
        $admin = $this->actingAsAdminWith(['devices.view', 'devices.block']);
        $target = User::factory()->app()->create();
        $token = $target->createToken('test')->accessToken;
        $device = UserDevice::factory()->for($target)->create(['token_id' => $token->id]);

        Livewire::test(Index::class)
            ->call('openBlockDialog', $device->ulid)
            ->set('blockReason', 'Reported stolen by the account owner.')
            ->call('block')
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
        $this->actingAsAdminWith(['devices.view', 'devices.block']);
        $device = UserDevice::factory()->for(User::factory()->app())->create();

        Livewire::test(Index::class)
            ->call('openBlockDialog', $device->ulid)
            ->call('block')
            ->assertHasErrors(['blockReason' => 'required'])
            ->assertSet('blockingUlid', $device->ulid)
            ->assertSee(__('devices.validation.block_reason_required'))
            ->set('blockReason', 'too short')
            ->call('block')
            ->assertHasErrors(['blockReason' => 'min'])
            ->assertSet('blockingUlid', $device->ulid)
            ->assertSee(__('devices.validation.block_reason_min', ['min' => 10]));

        $this->assertNull($device->fresh()->blocked_at);
    }

    public function test_revoking_a_device_deletes_its_token(): void
    {
        $this->actingAsAdminWith(['devices.view', 'devices.revoke']);
        $target = User::factory()->app()->create();
        $token = $target->createToken('test')->accessToken;
        $device = UserDevice::factory()->for($target)->create(['token_id' => $token->id]);

        Livewire::test(Index::class)
            ->call('confirmRevoke', $device->ulid)
            ->call('revoke');

        $this->assertNotNull($device->fresh()->revoked_at);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_unblocking_clears_the_block_but_does_not_restore_the_token(): void
    {
        $this->actingAsAdminWith(['devices.view', 'devices.unblock']);
        $device = UserDevice::factory()->for(User::factory()->app())->blocked()->create();

        Livewire::test(Index::class)->call('unblock', $device->ulid);

        $fresh = $device->fresh();
        $this->assertNull($fresh->blocked_at);
        $this->assertNull($fresh->token_id);
    }

    /** The global index is unscoped by design — an investigator can reach any device across any account. */
    public function test_the_index_can_revoke_a_device_belonging_to_any_account(): void
    {
        $this->actingAsAdminWith(['devices.view', 'devices.revoke']);
        $someUsersDevice = UserDevice::factory()->for(User::factory()->app())->create();

        Livewire::test(Index::class)
            ->call('confirmRevoke', $someUsersDevice->ulid)
            ->call('revoke');

        $this->assertNotNull($someUsersDevice->fresh()->revoked_at);
    }
}
