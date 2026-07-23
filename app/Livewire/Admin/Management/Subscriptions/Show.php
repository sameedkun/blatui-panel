<?php

namespace App\Livewire\Admin\Management\Subscriptions;

use App\Livewire\Admin\BaseShow;
use App\Livewire\Admin\Concerns\HasShowTabs;
use App\Livewire\Admin\Management\Subscriptions\Concerns\HandlesSubscriptionRowActions;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-focused detail page for a single subscription record — full plan/price
 * context, the owning user, its provider receipts, and the slice of that
 * user's audit trail concerning subscription changes. Header actions reuse
 * {@see HandlesSubscriptionRowActions} so a cancel/reactivate here runs
 * byte-for-byte the same code (and writes the same audit row) as from the
 * index — both ultimately call {@see SubscriptionService}.
 */
#[Layout('layouts.admin.app')]
class Show extends BaseShow
{
    use HandlesSubscriptionRowActions;
    use HasShowTabs;

    public function mount(Subscription $subscription): void
    {
        $this->initShow($subscription);
    }

    protected function indexRoute(): string
    {
        return 'admin.subscriptions.index';
    }

    protected function title(): string
    {
        $sub = $this->record;

        return ($sub->user?->name ?? 'Unknown User').' — '.($sub->plan?->name ?? 'Deleted Plan');
    }

    protected function viewPermission(): ?string
    {
        return 'subscriptions.manage';
    }

    protected function tabs(): array
    {
        return [
            'overview' => [
                'label' => 'Overview',
                'icon' => 'layout-grid',
                'view' => 'livewire.admin.management.subscriptions.show.tabs.overview',
            ],
            'receipts' => [
                'label' => 'Receipts',
                'icon' => 'receipt',
                'view' => 'livewire.admin.management.subscriptions.show.tabs.receipts',
                'data' => fn (): array => ['receipts' => $this->receipts()],
            ],
            'activity' => [
                'label' => 'Activity',
                'icon' => 'activity',
                'view' => 'livewire.admin.management.subscriptions.show.tabs.activity',
                'permission' => 'activity_logs.view',
                'data' => fn (): array => ['activities' => $this->relatedActivity()],
            ],
        ];
    }

    protected function receipts(): LengthAwarePaginator
    {
        return $this->record->receipts()
            ->latest()
            ->paginate(10, pageName: 'receipts_page');
    }

    /**
     * Subscription events are logged with the owning User as subject (see
     * {@see SubscriptionService}), never the Subscription row
     * itself — there is no per-row subject to key off, so this surfaces the
     * user's full subscription-activity trail rather than a precise per-row
     * one (the data model doesn't distinguish which historical subscription
     * a given log entry belongs to).
     */
    protected function relatedActivity(): LengthAwarePaginator
    {
        if (! $this->record->user) {
            return new LengthAwarePaginator([], 0, 10);
        }

        return Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $this->record->user_id)
            ->where('properties->type', 'like', 'subscription_%')
            ->with('causer')
            ->latest()
            ->paginate(10, pageName: 'activity_page');
    }

    /**
     * @return array<int, array{label: string, icon: string, value: string}>
     */
    public function statCards(): array
    {
        $sub = $this->record;

        return [
            ['label' => 'Status', 'icon' => 'activity', 'value' => $sub->status->label()],
            ['label' => 'Access Until', 'icon' => 'calendar-clock', 'value' => $sub->ends_at?->format('M d, Y') ?? '—'],
            ['label' => 'Amount Paid', 'icon' => 'banknote', 'value' => $sub->amount_paid !== null ? $sub->currency.' '.number_format((float) $sub->amount_paid, 2) : '—'],
            ['label' => 'Provider', 'icon' => 'credit-card', 'value' => $sub->provider->label()],
            ['label' => 'Auto-Renew', 'icon' => 'refresh-cw', 'value' => $sub->is_recurring ? 'Enabled' : 'Disabled'],
        ];
    }

    /** The subsequent subscription this one was replaced by (upgrade/downgrade chain), if any. */
    protected function nextSubscription(): ?Subscription
    {
        return Subscription::query()->where('previous_subscription_id', $this->record->id)->first();
    }

    public function render(): View
    {
        $this->refreshRecord();

        return view('livewire.admin.management.subscriptions.show', [
            'stats' => $this->statCards(),
            'nextSubscription' => $this->nextSubscription(),
        ]);
    }

    /** Pull fresh attributes so the header/stats reflect an action taken this request. */
    private function refreshRecord(): void
    {
        if ($fresh = $this->record->fresh(['user', 'plan', 'planPrice', 'previousSubscription'])) {
            $this->record = $fresh;
        }
    }
}
