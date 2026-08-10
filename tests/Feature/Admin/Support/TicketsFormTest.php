<?php

namespace Tests\Feature;

use App\Livewire\Admin\Support\Tickets\Form;
use App\Livewire\Admin\Support\Tickets\Show;
use App\Models\Ticket;
use App\Models\TicketCategory;
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

    public function test_changing_category_redirects_an_agent_who_loses_access_to_the_ticket(): void
    {
        $currentAgent = $this->actingAsAdminWith(['tickets.view', 'tickets.manage']);
        $newAgent = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $newAgent->givePermissionTo(['tickets.view', 'tickets.manage']);

        $currentCategory = TicketCategory::factory()->create();
        $currentCategory->agents()->attach($currentAgent);
        $newCategory = TicketCategory::factory()->create();
        $newCategory->agents()->attach($newAgent);
        $ticket = Ticket::factory()->for($currentCategory, 'category')->assignedTo($currentAgent)->create();

        Livewire::test(Show::class, ['ticket' => $ticket])
            ->call('updateCategory', $newCategory->id)
            ->assertRedirect(route('admin.tickets.index'));

        $ticket->refresh();
        $this->assertSame($newCategory->id, $ticket->category_id);
        $this->assertSame($newAgent->id, $ticket->assigned_to);
        $this->assertSame([
            'type' => 'success',
            'title' => __('tickets.toasts.category_changed', ['category' => $newCategory->name]),
        ], session('toast'));
    }

    public function test_reassigning_a_ticket_redirects_an_agent_who_loses_access(): void
    {
        $currentAgent = $this->actingAsAdminWith(['tickets.view', 'tickets.manage']);
        $newAgent = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $newAgent->givePermissionTo(['tickets.view', 'tickets.manage']);
        $ticket = Ticket::factory()->assignedTo($currentAgent)->create();

        Livewire::test(Show::class, ['ticket' => $ticket])
            ->call('reassignAgent', (string) $newAgent->id)
            ->assertRedirect(route('admin.tickets.index'));

        $ticket->refresh();
        $this->assertSame($newAgent->id, $ticket->assigned_to);
        $this->assertSame([
            'type' => 'success',
            'title' => __('tickets.toasts.reassigned', ['agent' => $newAgent->name]),
        ], session('toast'));
    }
}
