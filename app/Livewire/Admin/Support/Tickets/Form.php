<?php

namespace App\Livewire\Admin\Support\Tickets;

use App\Enum\TicketPriority;
use App\Livewire\Admin\BaseForm;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
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

    protected function validationAttributes(): array
    {
        return [
            'requesterId' => __('tickets.validation_attributes.requester'),
            'categoryId' => __('tickets.validation_attributes.category'),
            'subject' => __('tickets.validation_attributes.subject'),
            'message' => __('tickets.validation_attributes.message'),
            'priority' => __('tickets.validation_attributes.priority'),
        ];
    }

    protected function messages(): array
    {
        return [
            'requesterId.required' => __('tickets.validation.requester_required'),
            'requesterId.exists' => __('tickets.validation.requester_exists'),
            'categoryId.exists' => __('tickets.validation.category_exists'),
            'subject.required' => __('tickets.validation.subject_required'),
            'subject.string' => __('tickets.validation.subject_invalid'),
            'subject.max' => __('tickets.validation.subject_max', ['max' => 255]),
            'message.required' => __('tickets.validation.message_required'),
            'message.string' => __('tickets.validation.message_invalid'),
            'message.max' => __('tickets.validation.message_max', ['max' => 5000]),
            'priority.required' => __('tickets.validation.priority_required'),
            'priority.'.Enum::class => __('tickets.validation.priority_invalid'),
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

        session()->flash('toast', ['type' => 'success', 'title' => __('tickets.toasts.created')]);

        return $this->redirect(route('admin.tickets.show', $ticket));
    }

    public function render(): View
    {
        return view('livewire.admin.support.tickets.form', [
            'categoryOptions' => TicketCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'priorityOptions' => collect(TicketPriority::cases())->mapWithKeys(fn (TicketPriority $c) => [$c->value => $c->label()])->all(),
        ])->title(__('tickets.form.title'));
    }
}
