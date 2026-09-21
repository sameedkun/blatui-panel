<?php

namespace Tests\Feature\Admin\Staff;

use App\Livewire\Admin\Administration\Staff\Index;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffIndexTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    /**
     * Defense-in-depth mirroring UserShowTest::test_row_actions_reject_a_staff_account_even_if_forged():
     * the Staff module's actions must never reach an app user or guest row, even by forging
     * the id — that would let a `staff.*`-permission-only role bypass `users.*`/`guests.*`.
     */
    public function test_row_actions_reject_an_app_user_even_if_forged(): void
    {
        $this->actingAsSuperAdmin();
        $appUser = User::factory()->app()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Index::class)
            ->call('openBanDialog', $appUser->id);
    }

    public function test_force_delete_row_action_rejects_an_app_user_even_if_forged(): void
    {
        $this->actingAsSuperAdmin();
        $appUser = User::factory()->app()->create();
        $appUser->delete();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Index::class)
            ->call('confirmForceDelete', $appUser->id);
    }

    public function test_bulk_ban_only_affects_staff_even_if_an_app_user_id_is_selected(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $staffMember = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $appUser = User::factory()->app()->create();

        Livewire::test(Index::class)
            ->set('selectedIds', [$staffMember->id, $appUser->id, $admin->id])
            ->call('executeBulkBan');

        $this->assertNotNull($staffMember->fresh()->banned_at);
        $this->assertNull($appUser->fresh()->banned_at);
        $this->assertNull($admin->fresh()->banned_at); // self-action is also always excluded
    }
}
