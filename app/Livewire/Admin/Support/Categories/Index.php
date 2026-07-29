<?php

namespace App\Livewire\Admin\Support\Categories;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\TicketStatus;
use App\Livewire\Admin\BaseIndex;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Support\Categories\Concerns\HandlesCategoryRowActions;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

#[Layout('layouts.admin.app')]
class Index extends BaseIndex
{
    use HandlesCategoryRowActions;
    use LogsAdminActivity;

    public string $sortBy = 'sort_order';

    public string $sortDir = 'asc';

    public array $filters = [
        'status' => '',
    ];

    protected function baseQuery(): Builder
    {
        return TicketCategory::query()->withCount(['tickets', 'agents']);
    }

    protected function searchableColumns(): array
    {
        return ['name'];
    }

    protected function filterConfig(): array
    {
        return [
            'status' => [
                'label' => __('ticket_categories.fields.status'),
                'type' => 'select',
                'options' => ['active' => __('ticket_categories.status.active'), 'inactive' => __('ticket_categories.status.inactive')],
                'apply' => fn (Builder $q, string $v): Builder => match ($v) {
                    'active' => $q->where('is_active', true),
                    'inactive' => $q->where('is_active', false),
                    default => $q,
                },
            ],
        ];
    }

    protected function filterBarConfig(): array
    {
        return [
            'status' => [
                'label' => __('ticket_categories.fields.status'),
                'type' => 'select',
                'options' => ['active' => __('ticket_categories.status.active'), 'inactive' => __('ticket_categories.status.inactive')],
            ],
        ];
    }

    protected function statsConfig(): array
    {
        return [
            [
                'label' => __('ticket_categories.stats.total_categories'),
                'value' => fn () => TicketCategory::count(),
                'icon' => 'tags',
                'description' => __('ticket_categories.stats.all_categories'),
            ],
            [
                'label' => __('ticket_categories.status.active'),
                'value' => fn () => TicketCategory::where('is_active', true)->count(),
                'icon' => 'check-circle',
                'description' => __('ticket_categories.stats.available_for_routing'),
            ],
            [
                'label' => __('ticket_categories.stats.unassigned_tickets'),
                'value' => fn () => Ticket::whereNull('assigned_to')->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value])->count(),
                'icon' => 'user-x',
                'description' => __('ticket_categories.stats.no_agent_covers_them'),
            ],
            [
                'label' => __('ticket_categories.stats.agents_assigned'),
                'value' => fn () => TicketCategory::query()->join('category_agent', 'category_agent.category_id', '=', 'categories.id')->distinct('category_agent.user_id')->count('category_agent.user_id'),
                'icon' => 'users',
                'description' => __('ticket_categories.stats.distinct_staff'),
            ],
        ];
    }

    protected function bulkActionConfig(): array
    {
        return [
            [
                'key' => 'activate',
                'label' => __('ticket_categories.actions.activate'),
                'icon' => 'check-circle',
                'confirm' => true,
                'permission' => 'ticket_categories.edit',
            ],
            [
                'key' => 'deactivate',
                'label' => __('ticket_categories.actions.deactivate'),
                'icon' => 'circle-slash',
                'confirm' => true,
                'permission' => 'ticket_categories.edit',
            ],
            [
                'key' => 'delete',
                'label' => __('ticket_categories.actions.delete'),
                'icon' => 'trash',
                'confirm' => true,
                'variant' => 'destructive',
                'permission' => 'ticket_categories.delete',
            ],
        ];
    }

    public function executeBulkActivate(): void
    {
        $this->authorize('ticket_categories.edit');

        $ids = $this->selectedIds;
        $count = TicketCategory::whereIn('id', $ids)->update(['is_active' => true]);

        $this->logActivity(ActivityModule::TicketCategory, ActivityAction::Updated, null, [
            'bulk' => true,
            'category_ids' => $ids,
            'count' => $count,
            'attributes' => ['is_active' => true],
        ]);

        $this->clearSelection();
        $this->toastSuccess(trans_choice('ticket_categories.toasts.bulk_activated', $count, ['count' => $count]));
    }

    public function executeBulkDeactivate(): void
    {
        $this->authorize('ticket_categories.edit');

        $ids = $this->selectedIds;
        $count = TicketCategory::whereIn('id', $ids)->update(['is_active' => false]);

        $this->logActivity(ActivityModule::TicketCategory, ActivityAction::Updated, null, [
            'bulk' => true,
            'category_ids' => $ids,
            'count' => $count,
            'attributes' => ['is_active' => false],
        ]);

        $this->clearSelection();
        $this->toastSuccess(trans_choice('ticket_categories.toasts.bulk_deactivated', $count, ['count' => $count]));
    }

    public function executeBulkDelete(): void
    {
        $this->authorize('ticket_categories.delete');

        $categories = TicketCategory::whereIn('id', $this->selectedIds)->withCount('tickets')->get();
        $deletable = $categories->where('tickets_count', 0);
        $blocked = $categories->count() - $deletable->count();

        foreach ($deletable as $category) {
            $category->delete();
        }

        $this->logActivity(ActivityModule::TicketCategory, ActivityAction::Deleted, null, [
            'bulk' => true,
            'category_ids' => $deletable->pluck('id')->all(),
            'count' => $deletable->count(),
        ]);

        $this->clearSelection();

        $message = trans_choice('ticket_categories.toasts.bulk_deleted', $deletable->count(), ['count' => $deletable->count()]);
        if ($blocked > 0) {
            $message .= ' '.trans_choice('ticket_categories.toasts.bulk_skipped', $blocked, ['count' => $blocked]);
        }

        $this->toastSuccess($message);
    }

    public function render(): View
    {
        $categories = $this->getRecords();

        return view('livewire.admin.support.categories.index', [
            'categories' => $categories,
            'pageIds' => $categories->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
        ])->title(__('ticket_categories.title'));
    }
}
