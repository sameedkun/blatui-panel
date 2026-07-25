<?php

namespace App\Livewire\Admin\Support\Tickets;

use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Livewire\Admin\BaseShow;
use App\Livewire\Admin\Concerns\HasActivityDetailModal;
use App\Livewire\Admin\Concerns\HasShowTabs;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Support\Tickets\Concerns\HandlesTicketRowActions;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Services\TicketAssignmentService;
use App\Services\TicketService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\Activitylog\Models\Activity;

/**
 * Ticket conversation + management page. Header actions (assign-to-me,
 * close/reopen) reuse {@see HandlesTicketRowActions} so they run byte-for-byte
 * the same code as the index; the reply/status/priority/category/reassign
 * controls below all delegate to {@see TicketService}, the single source of
 * truth for ticket state changes.
 */
#[Layout('layouts.admin.app')]
class Show extends BaseShow
{
    use HandlesTicketRowActions;
    use HasActivityDetailModal;
    use HasShowTabs;
    use LogsAdminActivity;
    use WithFileUploads;

    public string $replyMessage = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $replyAttachments = [];

    /** Bumped after every reply so the file-upload's wire:key changes — its own
     *  Alpine preview list is client-side state that a normal morph wouldn't
     *  touch even after $replyAttachments is reset server-side; changing the
     *  key forces Livewire to tear down and remount it fresh. */
    public int $replyAttachmentsKey = 0;

    public function mount(Ticket $ticket): void
    {
        $this->initShow($ticket);

        // The index only lists what visibleTo() allows — enforce the same
        // restriction here so a non-super-admin can't reach an out-of-scope
        // ticket just by knowing its URL.
        abort_unless(Ticket::visibleTo(auth()->user())->whereKey($ticket->id)->exists(), 403);
    }

    protected function indexRoute(): string
    {
        return 'admin.tickets.index';
    }

    protected function title(): string
    {
        return $this->record->subject;
    }

    protected function viewPermission(): ?string
    {
        return 'tickets.manage';
    }

    protected function tabs(): array
    {
        return [
            'conversation' => [
                'label' => 'Conversation',
                'icon' => 'message-square',
                'view' => 'livewire.admin.support.tickets.show.tabs.conversation',
                'data' => fn (): array => [
                    'categoryOptions' => TicketCategory::orderBy('name')->pluck('name', 'id')->all(),
                    'agentOptions' => app(TicketAssignmentService::class)->eligibleAgentOptions(),
                    'statusOptions' => collect(TicketStatus::cases())->mapWithKeys(fn (TicketStatus $c) => [$c->value => $c->label()])->all(),
                    'priorityOptions' => collect(TicketPriority::cases())->mapWithKeys(fn (TicketPriority $c) => [$c->value => $c->label()])->all(),
                ],
            ],
            'activity' => [
                'label' => 'Activity',
                'icon' => 'activity',
                'view' => 'livewire.admin.support.tickets.show.tabs.activity',
                'permission' => 'activity_logs.view',
                'data' => fn (): array => [
                    'activities' => $this->recordActivity(),
                    'selectedActivity' => $this->selectedActivityDetail(),
                ],
            ],
        ];
    }

    protected function recordActivity(): LengthAwarePaginator
    {
        return Activity::forSubject($this->record)
            ->with('causer')
            ->latest()
            ->paginate(10);
    }

    public function reply(): void
    {
        $this->authorize('tickets.manage');

        $this->validate([
            'replyMessage' => ['required', 'string', 'max:5000'],
            'replyAttachments' => ['array', 'max:5'],
            'replyAttachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip'],
        ]);

        /** @var Ticket $ticket */
        $ticket = $this->record;

        app(TicketService::class)->reply($ticket, auth()->user(), $this->replyMessage, $this->replyAttachments);

        $this->replyMessage = '';
        $this->replyAttachments = [];
        $this->replyAttachmentsKey++;
        $this->toastSuccess('Reply sent.');
        $this->dispatch('scroll-to-latest-message');
    }

    public function updateStatus(string $status): void
    {
        $this->authorize('tickets.manage');

        $enum = TicketStatus::tryFrom($status);
        if (! $enum) {
            return;
        }

        /** @var Ticket $ticket */
        $ticket = $this->record;

        app(TicketService::class)->changeStatus($ticket, $enum, auth()->user());

        $this->toastSuccess('Status updated to '.$enum->label().'.');
    }

    public function updatePriority(string $priority): void
    {
        $this->authorize('tickets.manage');

        $enum = TicketPriority::tryFrom($priority);
        if (! $enum) {
            return;
        }

        /** @var Ticket $ticket */
        $ticket = $this->record;

        app(TicketService::class)->changePriority($ticket, $enum, auth()->user());

        $this->toastSuccess('Priority updated to '.$enum->label().'.');
    }

    public function updateCategory(int $categoryId): void
    {
        $this->authorize('tickets.manage');

        $category = TicketCategory::findOrFail($categoryId);

        /** @var Ticket $ticket */
        $ticket = $this->record;

        app(TicketService::class)->changeCategory($ticket, $category, auth()->user());

        $this->toastSuccess("Category changed to {$category->name}.");
    }

    /** Accepts a string so a blank "Unassigned" option from a native <select> coerces to null, not 0. */
    public function reassignAgent(?string $agentId): void
    {
        $this->authorize('tickets.manage');

        $agentId = $agentId !== null && $agentId !== '' ? (int) $agentId : null;
        $assignment = app(TicketAssignmentService::class);

        Validator::make(
            ['agentId' => $agentId],
            ['agentId' => ['nullable', 'integer', Rule::in(array_keys($assignment->eligibleAgentOptions()))]],
        )->validate();

        $agent = $agentId ? $assignment->eligibleAgentsQuery()->findOrFail($agentId) : null;

        /** @var Ticket $ticket */
        $ticket = $this->record;

        app(TicketService::class)->reassign($ticket, $agent, auth()->user());

        $this->toastSuccess($agent ? "Reassigned to {$agent->name}." : 'Unassigned.');
    }

    public function render(): View
    {
        $this->refreshRecord();

        return view('livewire.admin.support.tickets.show');
    }

    /** Pull fresh attributes so header badges reflect an action taken this request. */
    private function refreshRecord(): void
    {
        if ($fresh = $this->record->fresh(['user', 'category', 'agent', 'messages.user'])) {
            $this->record = $fresh;
        }
    }
}
