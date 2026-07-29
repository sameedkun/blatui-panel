<?php

namespace App\Livewire\Admin\Support\Tickets;

use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Livewire\Admin\BaseIndex;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Support\Tickets\Concerns\HandlesTicketRowActions;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Services\TicketAssignmentService;
use App\Services\TicketService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

#[Layout('layouts.admin.app')]
class Index extends BaseIndex
{
    use HandlesTicketRowActions;
    use LogsAdminActivity;

    public string $sortBy = 'created_at';

    public string $sortDir = 'desc';

    public array $filters = [
        'status' => '',
        'priority' => '',
        'category' => '',
        'assigned' => '',
    ];

    /** Agent picked in the "Assign Agent" bulk dialog — a native <select> always sends a string, even for the blank placeholder. */
    public string $bulkAssignAgentId = '';

    protected function baseQuery(): Builder
    {
        return Ticket::query()->visibleTo(auth()->user())->with(['user', 'category', 'agent']);
    }

    protected function searchableColumns(): array
    {
        return ['subject'];
    }

    protected function filterConfig(): array
    {
        return [
            'status' => [
                'label' => __('tickets.fields.status'),
                'type' => 'select',
                'options' => $this->statusOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $q->where('status', $v),
            ],
            'priority' => [
                'label' => __('tickets.fields.priority'),
                'type' => 'select',
                'options' => $this->priorityOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $q->where('priority', $v),
            ],
            'category' => [
                'label' => __('tickets.fields.category'),
                'type' => 'select',
                'options' => $this->categoryOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $q->where('category_id', $v),
            ],
            'assigned' => [
                'label' => __('tickets.filters.assigned'),
                'type' => 'select',
                'options' => $this->assignedOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $v === 'unassigned'
                    ? $q->whereNull('assigned_to')
                    : $q->where('assigned_to', $v),
            ],
        ];
    }

    protected function filterBarConfig(): array
    {
        return [
            'status' => ['label' => __('tickets.fields.status'), 'type' => 'select', 'options' => $this->statusOptions()],
            'priority' => ['label' => __('tickets.fields.priority'), 'type' => 'select', 'options' => $this->priorityOptions()],
            'category' => ['label' => __('tickets.fields.category'), 'type' => 'select', 'options' => $this->categoryOptions()],
            'assigned' => ['label' => __('tickets.filters.assigned'), 'type' => 'select', 'options' => $this->assignedOptions()],
        ];
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        return collect(TicketStatus::cases())->mapWithKeys(fn (TicketStatus $c) => [$c->value => $c->label()])->all();
    }

    /** @return array<string, string> */
    private function priorityOptions(): array
    {
        return collect(TicketPriority::cases())->mapWithKeys(fn (TicketPriority $c) => [$c->value => $c->label()])->all();
    }

    /** @return array<int, string> */
    private function categoryOptions(): array
    {
        return TicketCategory::orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int|string, string> */
    private function assignedOptions(): array
    {
        return ['unassigned' => __('tickets.unassigned')] + app(TicketAssignmentService::class)->eligibleAgentOptions();
    }

    protected function statsConfig(): array
    {
        return [
            [
                'label' => __('tickets.stats.total_tickets'),
                'value' => fn () => Ticket::visibleTo(auth()->user())->count(),
                'icon' => 'life-buoy',
                'description' => __('tickets.stats.all_time_submissions'),
            ],
            [
                'label' => __('tickets.stats.open'),
                'value' => fn () => Ticket::visibleTo(auth()->user())->whereIn('status', [TicketStatus::Open->value, TicketStatus::Pending->value])->count(),
                'icon' => 'inbox',
                'description' => __('tickets.stats.needs_attention'),
            ],
            [
                'label' => __('tickets.unassigned'),
                'value' => fn () => Ticket::visibleTo(auth()->user())->whereNull('assigned_to')->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value])->count(),
                'icon' => 'user-x',
                'description' => __('tickets.stats.no_agent_yet'),
            ],
            [
                'label' => __('tickets.stats.closed'),
                'value' => fn () => Ticket::visibleTo(auth()->user())->where('status', TicketStatus::Closed->value)->count(),
                'icon' => 'circle-check',
                'description' => __('tickets.stats.resolved_archived'),
            ],
        ];
    }

    protected function bulkActionConfig(): array
    {
        return [
            [
                'key' => 'close',
                'label' => __('tickets.actions.close_short'),
                'icon' => 'circle-check',
                'confirm' => true,
                'permission' => 'tickets.manage',
            ],
            [
                'key' => 'assign',
                'label' => __('tickets.actions.assign_agent'),
                'icon' => 'user-check',
                'confirm' => true,
                'permission' => 'tickets.manage',
                'dialog_event' => 'open-dialog-bulk-assign-tickets',
            ],
        ];
    }

    public function executeBulkClose(): void
    {
        $this->authorize('tickets.manage');

        $service = app(TicketService::class);
        $tickets = Ticket::visibleTo(auth()->user())
            ->whereIn('id', $this->selectedIds)
            ->where('status', '!=', TicketStatus::Closed->value)
            ->get();

        foreach ($tickets as $ticket) {
            $service->changeStatus($ticket, TicketStatus::Closed, auth()->user());
        }

        $this->clearSelection();
        $this->toastSuccess(trans_choice('tickets.toasts.bulk_closed', $tickets->count(), ['count' => $tickets->count()]));
    }

    public function executeBulkAssign(): void
    {
        $this->authorize('tickets.manage');

        $assignment = app(TicketAssignmentService::class);

        Validator::make(
            ['bulkAssignAgentId' => $this->bulkAssignAgentId],
            ['bulkAssignAgentId' => ['required', 'integer', Rule::in(array_keys($assignment->eligibleAgentOptions()))]],
            [
                'bulkAssignAgentId.required' => __('tickets.validation.agent_required'),
                'bulkAssignAgentId.integer' => __('tickets.validation.agent_invalid'),
                'bulkAssignAgentId.in' => __('tickets.validation.agent_invalid'),
            ],
            ['bulkAssignAgentId' => __('tickets.validation_attributes.agent')],
        )->validate();

        $agent = $assignment->eligibleAgentsQuery()->findOrFail($this->bulkAssignAgentId);
        $service = app(TicketService::class);

        $tickets = Ticket::visibleTo(auth()->user())->whereIn('id', $this->selectedIds)->get();
        foreach ($tickets as $ticket) {
            $service->reassign($ticket, $agent, auth()->user());
        }

        $this->bulkAssignAgentId = '';
        $this->clearSelection();
        $this->toastSuccess(trans_choice('tickets.toasts.bulk_assigned', $tickets->count(), [
            'agent' => $agent->name,
            'count' => $tickets->count(),
        ]));
    }

    public function render(): View
    {
        $tickets = $this->getRecords();

        return view('livewire.admin.support.tickets.index', [
            'tickets' => $tickets,
            'pageIds' => $tickets->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
            'agentOptions' => app(TicketAssignmentService::class)->eligibleAgentOptions(),
        ])->title(__('tickets.title'));
    }
}
