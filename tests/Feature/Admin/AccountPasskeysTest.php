<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Account\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Passkey;
use Tests\TestCase;

class AccountPasskeysTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $this->actingAs($staff);

        return $staff;
    }

    public function test_passkey_name_is_required_to_register_a_passkey(): void
    {
        $this->staff();

        Livewire::test(Index::class)
            ->set('passkeyName', '')
            ->call('validatePasskeyName')
            ->assertHasErrors(['passkeyName' => 'required']);
    }

    public function test_storing_a_passkey_logs_activity_and_toasts(): void
    {
        $staff = $this->staff();

        config(['passkeys.actions.store_passkey' => FakeStorePasskeyAction::class]);

        Livewire::test(Index::class)
            ->set('passkeyName', 'Work laptop')
            ->call('validatePasskeyName')
            ->assertHasNoErrors()
            ->call('storePasskey', 'irrelevant-because-faked')
            ->assertHasNoErrors()
            ->assertSet('passkeyName', '')
            ->assertDispatched('toast', type: 'success', title: __('account.toasts.passkey_created'), description: null);

        $this->assertDatabaseHas('passkeys', [
            'authenticatable_id' => $staff->id,
            'name' => 'Work laptop',
        ]);

        $this->assertTrue(
            Activity::where('event', 'created')
                ->where('causer_id', $staff->id)
                ->where('properties->type', 'passkey_created')
                ->where('properties->passkey_name', 'Work laptop')
                ->exists()
        );
    }

    public function test_deleting_a_passkey_removes_it_and_logs_activity(): void
    {
        $staff = $this->staff();
        $passkey = Passkey::factory()->create(['authenticatable_id' => $staff->id, 'name' => 'Old key']);

        Livewire::test(Index::class)
            ->call('deletePasskey', $passkey->id)
            ->assertDispatched('toast', type: 'success', title: __('account.toasts.passkey_deleted'), description: null);

        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);

        $this->assertTrue(
            Activity::where('event', 'deleted')
                ->where('causer_id', $staff->id)
                ->where('properties->type', 'passkey_deleted')
                ->where('properties->passkey_name', 'Old key')
                ->exists()
        );
    }

    public function test_deleting_another_staff_members_passkey_does_nothing(): void
    {
        $this->staff();
        $otherStaff = User::factory()->create(['type' => 'staff']);
        $othersPasskey = Passkey::factory()->create(['authenticatable_id' => $otherStaff->id]);

        Livewire::test(Index::class)->call('deletePasskey', $othersPasskey->id);

        $this->assertDatabaseHas('passkeys', ['id' => $othersPasskey->id]);
    }
}

class FakeStorePasskeyAction extends StorePasskeyAction
{
    public function execute(
        HasPasskeys $authenticatable,
        string $passkeyJson,
        string $passkeyOptionsJson,
        string $hostName,
        array $additionalProperties = [],
    ): Passkey {
        return Passkey::factory()->for($authenticatable, 'authenticatable')->create($additionalProperties);
    }
}
