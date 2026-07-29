<?php

namespace App\Livewire\Admin\Management\Plans;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\BaseShow;
use App\Livewire\Admin\Concerns\HasActivityDetailModal;
use App\Livewire\Admin\Concerns\HasShowTabs;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Management\Plans\Concerns\HandlesPlanRowActions;
use App\Models\Plan;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-focused detail page for a single plan — pricing, payment-provider
 * mappings, every subscription ever sold against it, and its audit trail.
 * Header actions reuse {@see HandlesPlanRowActions} so a toggle/delete here
 * runs byte-for-byte the same code (and writes the same audit row) as the
 * same action from the index. `delete()` is redefined below because, unlike
 * the index, this page has nowhere to stay once its own record is gone.
 */
#[Layout('layouts.admin.app')]
class Show extends BaseShow
{
    use HandlesPlanRowActions;
    use HasActivityDetailModal;
    use HasShowTabs;
    use LogsAdminActivity;
    use WithPagination;

    /** Subscriptions-tab status filter. */
    #[Url]
    public string $subsStatus = '';

    public function mount(Plan $plan): void
    {
        $this->initShow($plan);
    }

    protected function indexRoute(): string
    {
        return 'admin.plans.index';
    }

    protected function title(): string
    {
        return $this->record->name;
    }

    protected function viewPermission(): ?string
    {
        return 'plans.manage';
    }

    /**
     * The page shell (header → stats → tabs) is stable; a new module integrates
     * by registering a tab here without touching the shell.
     */
    protected function tabs(): array
    {
        return [
            'overview' => [
                'label' => __('plans.tabs.overview'),
                'icon' => 'layout-grid',
                'view' => 'livewire.admin.management.plans.show.tabs.overview',
            ],
            'prices' => [
                'label' => __('plans.tabs.prices'),
                'icon' => 'credit-card',
                'view' => 'livewire.admin.management.plans.show.tabs.prices',
            ],
            'subscriptions' => [
                'label' => __('plans.tabs.subscriptions'),
                'icon' => 'users',
                'view' => 'livewire.admin.management.plans.show.tabs.subscriptions',
                'data' => fn (): array => [
                    'subscriptions' => $this->subscriptions(),
                ],
            ],
            'activity' => [
                'label' => __('plans.tabs.activity'),
                'icon' => 'activity',
                'view' => 'livewire.admin.management.plans.show.tabs.activity',
                'permission' => 'activity_logs.view',
                'data' => fn (): array => [
                    'activities' => $this->recordActivity(),
                    'selectedActivity' => $this->selectedActivityDetail(),
                ],
            ],
        ];
    }

    public function updatedSubsStatus(): void
    {
        $this->resetPage();
    }

    protected function subscriptions(): LengthAwarePaginator
    {
        return $this->record->subscriptions()
            ->with(['user', 'planPrice'])
            ->when($this->subsStatus !== '', fn ($q) => $q->where('status', $this->subsStatus))
            ->latest('starts_at')
            ->paginate(10);
    }

    /** Paginated audit trail for this record — powers the Activity tab. */
    protected function recordActivity(): LengthAwarePaginator
    {
        return Activity::forSubject($this->record)
            ->with('causer')
            ->latest()
            ->paginate(10);
    }

    /**
     * Summary cards under the header.
     *
     * @return array<int, array{label: string, icon: string, value: string}>
     */
    public function statCards(): array
    {
        $plan = $this->record;
        $counts = $plan->subscriptions()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $active = (int) ($counts['trialing'] ?? 0) + (int) ($counts['active'] ?? 0) + (int) ($counts['grace'] ?? 0);
        $inactive = (int) $counts->sum() - $active;

        return [
            ['label' => __('plans.show_stats.total_subscriptions'), 'icon' => 'users', 'value' => (string) $counts->sum()],
            ['label' => __('plans.status.active'), 'icon' => 'check-circle', 'value' => (string) $active],
            ['label' => __('plans.show_stats.cancelled_expired'), 'icon' => 'circle-slash', 'value' => (string) $inactive],
            ['label' => __('plans.show_stats.total_revenue'), 'icon' => 'banknote', 'value' => number_format((float) $plan->subscriptions()->sum('amount_paid'), 2)],
            ['label' => __('plans.tabs.prices'), 'icon' => 'tag', 'value' => (string) $plan->prices()->count()],
        ];
    }

    /**
     * Delete removes the record, so — unlike the index — the profile has
     * nowhere to stay and returns to the list. Same guard and audit call as
     * {@see HandlesPlanRowActions::delete()}; only the outcome differs.
     */
    public function delete()
    {
        $this->authorize('plans.delete');

        $plan = Plan::findOrFail($this->deletingId);

        if ($this->hasSubscriptions($plan)) {
            $this->deletingId = null;
            $this->toastError(
                __('plans.toasts.cannot_delete_with_subscriptions', ['name' => $plan->name]),
                __('plans.toasts.deactivate_to_retire'),
            );

            return;
        }

        $name = $plan->name;
        $plan->delete();

        $this->logActivity(ActivityModule::Plan, ActivityAction::Deleted, null, [
            'attributes' => ['name' => $name],
        ]);

        session()->flash('toast', ['type' => 'success', 'title' => __('plans.toasts.deleted', ['name' => $name])]);

        return $this->redirect(route('admin.plans.index'));
    }

    public function render(): View
    {
        $this->refreshRecord();

        return view('livewire.admin.management.plans.show', [
            'stats' => $this->statCards(),
        ])->title(__('plans.title').' — '.$this->title());
    }

    /** Pull fresh attributes so header badges reflect an action taken this request. */
    private function refreshRecord(): void
    {
        if ($fresh = $this->record->fresh()) {
            $this->record = $fresh;
        }
    }
}
