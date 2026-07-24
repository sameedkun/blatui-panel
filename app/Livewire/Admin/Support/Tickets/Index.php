<?php

namespace App\Livewire\Admin\Support\Tickets;

use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Livewire\Admin\BaseIndex;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Support\Tickets\Concerns\HandlesTicketRowActions;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.admin.app')]
#[Title('Tickets')]
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
        return Ticket::query()->with(['user', 'category', 'agent']);
    }

    protected function searchableColumns(): array
    {
        return ['subject'];
    }

    protected function filterConfig(): array
    {
        return [
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => $this->statusOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $q->where('status', $v),
            ],
            'priority' => [
                'label' => 'Priority',
                'type' => 'select',
                'options' => $this->priorityOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $q->where('priority', $v),
            ],
            'category' => [
                'label' => 'Category',
                'type' => 'select',
                'options' => $this->categoryOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $q->where('category_id', $v),
            ],
            'assigned' => [
                'label' => 'Assigned',
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
            'status' => ['label' => 'Status', 'type' => 'select', 'options' => $this->statusOptions()],
            'priority' => ['label' => 'Priority', 'type' => 'select', 'options' => $this->priorityOptions()],
            'category' => ['label' => 'Category', 'type' => 'select', 'options' => $this->categoryOptions()],
            'assigned' => ['label' => 'Assigned', 'type' => 'select', 'options' => $this->assignedOptions()],
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
        return ['unassigned' => 'Unassigned'] + User::staff()->permission(['tickets.view', 'tickets.manage'])->orderBy('name')->pluck('name', 'id')->all();
    }

    protected function statsConfig(): array
    {
        return [
            [
                'label' => 'Total Tickets',
                'value' => fn () => Ticket::count(),
                'icon' => 'life-buoy',
                'description' => 'All-time submissions',
            ],
            [
                'label' => 'Open',
                'value' => fn () => Ticket::whereIn('status', [TicketStatus::Open->value, TicketStatus::Pending->value])->count(),
                'icon' => 'inbox',
                'description' => 'Needs attention',
            ],
            [
                'label' => 'Unassigned',
                'value' => fn () => Ticket::whereNull('assigned_to')->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value])->count(),
                'icon' => 'user-x',
                'description' => 'No agent yet',
            ],
            [
                'label' => 'Closed',
                'value' => fn () => Ticket::where('status', TicketStatus::Closed->value)->count(),
                'icon' => 'circle-check',
                'description' => 'Resolved & archived',
            ],
        ];
    }

    protected function bulkActionConfig(): array
    {
        return [
            [
                'key' => 'close',
                'label' => 'Close',
                'icon' => 'circle-check',
                'confirm' => true,
                'permission' => 'tickets.manage',
            ],
            [
                'key' => 'assign',
                'label' => 'Assign Agent',
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
        $tickets = Ticket::whereIn('id', $this->selectedIds)
            ->where('status', '!=', TicketStatus::Closed->value)
            ->get();

        foreach ($tickets as $ticket) {
            $service->changeStatus($ticket, TicketStatus::Closed, auth()->user());
        }

        $this->clearSelection();
        $this->toastSuccess("{$tickets->count()} tickets closed.");
    }

    public function executeBulkAssign(): void
    {
        $this->authorize('tickets.manage');

        Validator::make(
            ['bulkAssignAgentId' => $this->bulkAssignAgentId],
            ['bulkAssignAgentId' => ['required', 'integer', 'exists:users,id']],
        )->validate();

        $agent = User::staff()->findOrFail($this->bulkAssignAgentId);
        $service = app(TicketService::class);

        $tickets = Ticket::whereIn('id', $this->selectedIds)->get();
        foreach ($tickets as $ticket) {
            $service->reassign($ticket, $agent, auth()->user());
        }

        $this->bulkAssignAgentId = '';
        $this->clearSelection();
        $this->toastSuccess("Assigned {$agent->name} to {$tickets->count()} tickets.");
    }

    public function render(): View
    {
        $tickets = $this->getRecords();

        return view('livewire.admin.support.tickets.index', [
            'tickets' => $tickets,
            'pageIds' => $tickets->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
            'agentOptions' => User::staff()->permission(['tickets.view', 'tickets.manage'])->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
