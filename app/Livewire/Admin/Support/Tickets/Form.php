<?php

namespace App\Livewire\Admin\Support\Tickets;

use App\Enum\TicketPriority;
use App\Livewire\Admin\BaseForm;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;

/**
 * Logs a ticket on behalf of an existing app user — the entry point for
 * phone/email support today, and what exercises category auto-assignment
 * end-to-end until a public-facing submission channel exists.
 */
#[Layout('layouts.admin.app')]
class Form extends BaseForm
{
    /** Bound to requester user ID. */
    public $requesterId = '';

    public string $userSearch = '';

    public $categoryId = '';

    public string $subject = '';

    public string $message = '';

    public string $priority = 'medium';

    public function selectUser(int $id): void
    {
        $this->requesterId = $id;
        $this->userSearch = '';
    }

    public function clearUser(): void
    {
        $this->requesterId = '';
        $this->userSearch = '';
    }

    #[Computed]
    public function userSearchResults()
    {
        $term = trim($this->userSearch);

        return User::appUsers()
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->take(10)
            ->get();
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        if (! $this->requesterId) {
            return null;
        }

        return User::appUsers()->find($this->requesterId);
    }

    protected function indexRoute(): string
    {
        return 'admin.tickets.index';
    }

    protected function rules(): array
    {
        return [
            'requesterId' => ['required', 'exists:users,id'],
            'categoryId' => ['nullable', 'exists:categories,id'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
        ];
    }

    public function save(): mixed
    {
        $this->validate();

        $requester = User::appUsers()->findOrFail($this->requesterId);
        $category = $this->categoryId !== '' ? TicketCategory::findOrFail($this->categoryId) : null;

        $ticket = app(TicketService::class)->create(
            $requester,
            $category,
            $this->subject,
            $this->message,
            TicketPriority::from($this->priority),
            auth()->user(),
        );

        session()->flash('toast', ['type' => 'success', 'title' => 'Ticket created.']);

        return $this->redirect(route('admin.tickets.show', $ticket));
    }

    public function render(): View
    {
        return view('livewire.admin.support.tickets.form', [
            'categoryOptions' => TicketCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'priorityOptions' => collect(TicketPriority::cases())->mapWithKeys(fn (TicketPriority $c) => [$c->value => $c->label()])->all(),
        ]);
    }
}
