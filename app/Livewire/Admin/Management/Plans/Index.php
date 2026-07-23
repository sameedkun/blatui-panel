<?php

namespace App\Livewire\Admin\Management\Plans;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\BaseIndex;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Management\Plans\Concerns\HandlesPlanRowActions;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.admin.app')]
#[Title('Plans')]
class Index extends BaseIndex
{
    use HandlesPlanRowActions;
    use LogsAdminActivity;

    public string $sortBy = 'sort_order';

    public string $sortDir = 'asc';

    public array $filters = [
        'status' => '',
        'best_deal' => '',
    ];

    protected function baseQuery(): Builder
    {
        return Plan::query()
            ->withCount('subscriptions')
            ->with(['prices' => fn ($q) => $q->orderBy('amount')]);
    }

    protected function searchableColumns(): array
    {
        return ['name', 'slug', 'description'];
    }

    // ── Filters ───────────────────────────────────────────────────────────────

    protected function filterConfig(): array
    {
        return [
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => ['active' => 'Active', 'inactive' => 'Inactive'],
                'apply' => fn (Builder $q, string $v): Builder => match ($v) {
                    'active' => $q->where('is_active', true),
                    'inactive' => $q->where('is_active', false),
                    default => $q,
                },
            ],
            'best_deal' => [
                'label' => 'Best Deal',
                'type' => 'select',
                'options' => ['yes' => 'Yes', 'no' => 'No'],
                'apply' => fn (Builder $q, string $v): Builder => match ($v) {
                    'yes' => $q->where('is_best_deal', true),
                    'no' => $q->where('is_best_deal', false),
                    default => $q,
                },
            ],
        ];
    }

    protected function filterBarConfig(): array
    {
        return [
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => ['active' => 'Active', 'inactive' => 'Inactive'],
            ],
            'best_deal' => [
                'label' => 'Best Deal',
                'type' => 'select',
                'options' => ['yes' => 'Yes', 'no' => 'No'],
            ],
        ];
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    protected function statsConfig(): array
    {
        return [
            [
                'label' => 'Total Plans',
                'value' => fn () => Plan::count(),
                'icon' => 'layers',
                'description' => 'All plans',
            ],
            [
                'label' => 'Active',
                'value' => fn () => Plan::where('is_active', true)->count(),
                'icon' => 'check-circle',
                'description' => 'Visible for purchase',
            ],
            [
                'label' => 'Inactive',
                'value' => fn () => Plan::where('is_active', false)->count(),
                'icon' => 'circle-slash',
                'description' => 'Retired or hidden',
            ],
            [
                'label' => 'Active Subscriptions',
                'value' => fn () => Subscription::whereIn('status', ['trialing', 'active', 'grace'])->count(),
                'icon' => 'credit-card',
                'description' => 'Across all plans',
            ],
        ];
    }

    // ── Bulk actions ─────────────────────────────────────────────────────────

    protected function bulkActionConfig(): array
    {
        return [
            [
                'key' => 'activate',
                'label' => 'Activate',
                'icon' => 'check-circle',
                'confirm' => true,
                'permission' => 'plans.edit',
            ],
            [
                'key' => 'deactivate',
                'label' => 'Deactivate',
                'icon' => 'circle-slash',
                'confirm' => true,
                'permission' => 'plans.edit',
            ],
            [
                'key' => 'delete',
                'label' => 'Delete',
                'icon' => 'trash',
                'confirm' => true,
                'variant' => 'destructive',
                'permission' => 'plans.delete',
            ],
        ];
    }

    public function executeBulkActivate(): void
    {
        $this->authorize('plans.edit');

        $ids = $this->selectedIds;
        $count = Plan::whereIn('id', $ids)->update(['is_active' => true]);

        $this->logActivity(ActivityModule::Plan, ActivityAction::Updated, null, [
            'bulk' => true,
            'plan_ids' => $ids,
            'count' => $count,
            'attributes' => ['is_active' => true],
        ]);

        $this->clearSelection();
        $this->toastSuccess("{$count} plans activated.");
    }

    public function executeBulkDeactivate(): void
    {
        $this->authorize('plans.edit');

        $ids = $this->selectedIds;
        $count = Plan::whereIn('id', $ids)->update(['is_active' => false]);

        $this->logActivity(ActivityModule::Plan, ActivityAction::Updated, null, [
            'bulk' => true,
            'plan_ids' => $ids,
            'count' => $count,
            'attributes' => ['is_active' => false],
        ]);

        $this->clearSelection();
        $this->toastSuccess("{$count} plans deactivated.");
    }

    public function executeBulkDelete(): void
    {
        $this->authorize('plans.delete');

        $plans = Plan::whereIn('id', $this->selectedIds)->withCount('subscriptions')->get();
        $deletable = $plans->where('subscriptions_count', 0);
        $blocked = $plans->count() - $deletable->count();

        foreach ($deletable as $plan) {
            $plan->delete();
        }

        $this->logActivity(ActivityModule::Plan, ActivityAction::Deleted, null, [
            'bulk' => true,
            'plan_ids' => $deletable->pluck('id')->all(),
            'count' => $deletable->count(),
        ]);

        $this->clearSelection();

        $message = "{$deletable->count()} plans deleted.";
        if ($blocked > 0) {
            $message .= " {$blocked} skipped (has subscriptions).";
        }

        $this->toastSuccess($message);
    }

    public function render(): View
    {
        $plans = $this->getRecords();

        return view('livewire.admin.management.plans.index', [
            'plans' => $plans,
            'pageIds' => $plans->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
        ]);
    }
}
