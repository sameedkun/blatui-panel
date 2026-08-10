<?php

namespace Tests\Feature;

use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Livewire\Admin\Support\Categories\Form as CategoryForm;
use App\Livewire\Admin\Support\Tickets\Form as TicketForm;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportLocalizationTest extends TestCase
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

    public function test_english_and_turkish_support_translations_have_matching_keys(): void
    {
        foreach (['tickets', 'ticket_categories'] as $group) {
            $englishKeys = array_keys(Arr::dot(Lang::get($group, [], 'en')));
            $turkishKeys = array_keys(Arr::dot(Lang::get($group, [], 'tr')));

            sort($englishKeys);
            sort($turkishKeys);

            $this->assertSame($englishKeys, $turkishKeys, "Translation keys differ for {$group}.");
        }
    }

    public function test_support_pages_and_browser_titles_use_the_request_locale(): void
    {
        $admin = $this->actingAsAdminWith([
            'panel.access-admin',
            'tickets.view',
            'tickets.create',
            'tickets.manage',
            'ticket_categories.view',
            'ticket_categories.create',
            'ticket_categories.edit',
            'ticket_categories.delete',
            'activity_logs.view',
            'users.manage',
        ]);

        $category = TicketCategory::factory()->create(['name' => 'Faturalandırma']);
        $ticket = Ticket::factory()
            ->for($category, 'category')
            ->assignedTo($admin)
            ->create(['subject' => 'Bağlantı sorunu']);

        $ticketIndex = $this->withCookie('locale', 'tr')->get(route('admin.tickets.index'));
        $ticketIndex->assertOk();
        $ticketIndex->assertSee('<title>'.__('tickets.title').' — '.config('app.name').'</title>', false);
        $ticketIndex->assertSee(__('tickets.subtitle'));

        $ticketCreate = $this->withCookie('locale', 'tr')->get(route('admin.tickets.create'));
        $ticketCreate->assertOk();
        $ticketCreate->assertSee('<title>'.__('tickets.form.title').' — '.config('app.name').'</title>', false);
        $ticketCreate->assertSee(__('tickets.form.description'));

        $ticketShow = $this->withCookie('locale', 'tr')->get(route('admin.tickets.show', $ticket));
        $ticketShow->assertOk();
        $ticketShow->assertSee(
            '<title>'.__('tickets.show.page_title', ['subject' => $ticket->subject]).' — '.config('app.name').'</title>',
            false,
        );
        $ticketShow->assertSee(__('tickets.conversation.title'));

        $categoryIndex = $this->withCookie('locale', 'tr')->get(route('admin.ticket-categories.index'));
        $categoryIndex->assertOk();
        $categoryIndex->assertSee('<title>'.__('ticket_categories.title').' — '.config('app.name').'</title>', false);
        $categoryIndex->assertSee(__('ticket_categories.subtitle'));

        $categoryCreate = $this->withCookie('locale', 'tr')->get(route('admin.ticket-categories.create'));
        $categoryCreate->assertOk();
        $categoryCreate->assertSee(
            '<title>'.__('ticket_categories.form.create_title').' — '.config('app.name').'</title>',
            false,
        );
        $categoryCreate->assertSee(__('ticket_categories.form.create_description'));

        $categoryEdit = $this->withCookie('locale', 'tr')->get(route('admin.ticket-categories.edit', $category));
        $categoryEdit->assertOk();
        $categoryEdit->assertSee(
            '<title>'.__('ticket_categories.form.edit_title').' — '.config('app.name').'</title>',
            false,
        );
        $categoryEdit->assertSee(__('ticket_categories.form.edit_description'));
    }

    public function test_support_validation_enum_labels_and_success_messages_use_the_active_locale(): void
    {
        App::setLocale('tr');
        $this->actingAsAdminWith([
            'tickets.view',
            'tickets.create',
            'tickets.manage',
            'ticket_categories.view',
            'ticket_categories.create',
            'ticket_categories.edit',
            'ticket_categories.delete',
        ]);

        Livewire::test(TicketForm::class)
            ->call('save')
            ->assertHasErrors(['requesterId' => 'required', 'subject' => 'required', 'message' => 'required'])
            ->assertSee(__('tickets.validation.requester_required'))
            ->assertSee(__('tickets.validation.subject_required'))
            ->assertSee(__('tickets.validation.message_required'));

        $requester = User::factory()->app()->create();

        Livewire::test(TicketForm::class)
            ->call('selectUser', $requester->id)
            ->set('subject', 'Hesap erişim sorunu')
            ->set('message', 'Hesabıma erişemiyorum.')
            ->set('priority', TicketPriority::Urgent->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(__('tickets.toasts.created'), session('toast.title'));

        Livewire::test(CategoryForm::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required'])
            ->assertSee(__('ticket_categories.validation.name_required'));

        Livewire::test(CategoryForm::class)
            ->set('name', 'Teknik Destek')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.ticket-categories.index'));

        $this->assertSame(
            __('ticket_categories.toasts.created', ['name' => 'Teknik Destek']),
            session('toast.title'),
        );
        $this->assertSame(__('enums.ticket_status.Open'), TicketStatus::Open->label());
        $this->assertSame(__('enums.ticket_priority.Urgent'), TicketPriority::Urgent->label());
    }
}
