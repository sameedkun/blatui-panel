<?php

namespace Tests\Feature;

use App\Livewire\Admin\Support\Tickets\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketsFormTest extends TestCase
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

    public function test_user_search_and_select_in_tickets_form(): void
    {
        $this->actingAsAdminWith(['tickets.view', 'tickets.reply']);

        $appUser = User::factory()->app()->create(['name' => 'John Requester', 'email' => 'john@example.com']);
        $staffUser = User::factory()->create(['type' => 'staff', 'name' => 'John Staff', 'email' => 'staff@example.com']);

        Livewire::test(Form::class)
            ->set('userSearch', 'John')
            ->assertSee('John Requester')
            ->assertSee('john@example.com')
            ->assertDontSee('John Staff')
            ->call('selectUser', $appUser->id)
            ->assertSet('requesterId', $appUser->id)
            ->assertSet('userSearch', '')
            ->call('clearUser')
            ->assertSet('requesterId', '');
    }

    public function test_creating_a_ticket_with_selected_user(): void
    {
        $admin = $this->actingAsAdminWith(['tickets.view', 'tickets.reply']);

        $appUser = User::factory()->app()->create(['name' => 'John Requester', 'email' => 'john@example.com']);

        Livewire::test(Form::class)
            ->call('selectUser', $appUser->id)
            ->set('subject', 'Need help with account')
            ->set('message', 'Cannot reset password')
            ->set('priority', 'high')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tickets', [
            'user_id' => $appUser->id,
            'subject' => 'Need help with account',
            'priority' => 'high',
        ]);
    }
}
