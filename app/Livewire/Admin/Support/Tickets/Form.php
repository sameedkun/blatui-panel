<?php

namespace App\Livewire\Admin\Support\Tickets;

use App\Enum\TicketPriority;
use App\Livewire\Admin\BaseForm;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

/**
 * Logs a ticket on behalf of an existing app user — the entry point for
 * phone/email support today, and what exercises category auto-assignment
 * end-to-end until a public-facing submission channel exists.
 */
#[Layout('layouts.admin.app')]
class Form extends BaseForm
{
    /** Bound to a native <select> — always a string over the wire, even for the blank placeholder. */
    public $requesterId = '';

    public $categoryId = '';

    public string $subject = '';

    public string $message = '';

    public string $priority = 'medium';

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
            'requesterOptions' => User::appUsers()->orderBy('name')->pluck('name', 'id')->all(),
            'categoryOptions' => TicketCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'priorityOptions' => collect(TicketPriority::cases())->mapWithKeys(fn (TicketPriority $c) => [$c->value => $c->label()])->all(),
        ]);
    }
}
