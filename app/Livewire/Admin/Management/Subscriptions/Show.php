<?php

namespace App\Livewire\Admin\Management\Subscriptions;

use App\Contracts\ProviderNotification;
use App\Livewire\Admin\BaseShow;
use App\Livewire\Admin\Concerns\HasShowTabs;
use App\Livewire\Admin\Management\Subscriptions\Concerns\HandlesSubscriptionRowActions;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use App\Support\WebhookNotificationRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use LogicException;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-focused detail page for a single subscription record — full plan/price
 * context, the owning user, its financial transactions, the provider
 * notifications for its contract, and the slice of that
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

        return ($sub->user?->name ?? __('subscriptions.status.unknown_user'))
            .' — '.($sub->plan?->name ?? __('subscriptions.status.deleted_plan'));
    }

    protected function viewPermission(): ?string
    {
        return 'subscriptions.manage';
    }

    protected function tabs(): array
    {
        return [
            'overview' => [
                'label' => __('subscriptions.tabs.overview'),
                'icon' => 'layout-grid',
                'view' => 'livewire.admin.management.subscriptions.show.tabs.overview',
            ],
            'transactions' => [
                'label' => __('subscriptions.tabs.transactions'),
                'icon' => 'receipt',
                'view' => 'livewire.admin.management.subscriptions.show.tabs.transactions',
                'data' => fn (): array => ['transactions' => $this->transactions()],
            ],
            'webhook_notifications' => [
                'label' => __('webhook_notifications.tab.label'),
                'icon' => 'webhook',
                'view' => 'livewire.admin.management.subscriptions.show.tabs.webhook-notifications',
                'permission' => 'webhook_notifications.view',
                'data' => fn (): array => ['notifications' => $this->webhookNotifications()],
            ],
            'activity' => [
                'label' => __('subscriptions.tabs.activity'),
                'icon' => 'activity',
                'view' => 'livewire.admin.management.subscriptions.show.tabs.activity',
                'permission' => 'activity_logs.view',
                'data' => fn (): array => ['activities' => $this->relatedActivity()],
            ],
        ];
    }

    protected function transactions(): LengthAwarePaginator
    {
        return $this->subscription()->transactions()
            ->latest('purchased_at')
            ->latest('id')
            ->paginate(10, pageName: 'transactions_page');
    }

    /** Most recent provider notifications shown for a contract. */
    private const int NOTIFICATION_LIMIT = 50;

    /**
     * Every raw provider notification about this subscription's contract —
     * matched on the original transaction id its transactions carry (the
     * `original_transaction_id` column every provider notification table
     * shares by convention), so state-only events (auto-renew off, billing
     * failure, expiry) and not-yet-applied deliveries show up too, not just
     * the ones that moved money.
     *
     * @return Collection<int, ProviderNotification&Model>
     */
    protected function webhookNotifications(): Collection
    {
        $subscription = $this->subscription();
        $model = WebhookNotificationRegistry::modelFor($subscription->provider);
        $originalIds = $subscription->transactions()
            ->whereNotNull('provider_original_id')
            ->distinct()
            ->pluck('provider_original_id');

        if (! $model || $originalIds->isEmpty()) {
            return collect();
        }

        return $model::query()
            ->whereIn('original_transaction_id', $originalIds)
            ->latest('id')
            ->limit(self::NOTIFICATION_LIMIT)
            ->get();
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
            ['label' => __('subscriptions.fields.status'), 'icon' => 'activity', 'value' => $sub->status->label()],
            ['label' => __('subscriptions.access_until'), 'icon' => 'calendar-clock', 'value' => $sub->ends_at?->translatedFormat('M d, Y') ?? '—'],
            ['label' => __('subscriptions.fields.net_paid'), 'icon' => 'banknote', 'value' => $this->netPaidLabel($this->subscription())],
            ['label' => __('subscriptions.fields.provider'), 'icon' => 'credit-card', 'value' => $sub->provider->label()],
            ['label' => __('subscriptions.fields.auto_renew'), 'icon' => 'refresh-cw', 'value' => $sub->is_recurring ? __('subscriptions.status.enabled') : __('subscriptions.status.disabled')],
        ];
    }

    /** The bound record — always a subscription on this page ({@see mount()}). */
    private function subscription(): Subscription
    {
        return $this->record instanceof Subscription
            ? $this->record
            : throw new LogicException('The subscription page is bound to a '.$this->record::class.'.');
    }

    /** Net collected per currency ("$19.98 · €9.49"), or the grant source when nothing was paid. */
    private function netPaidLabel(Subscription $sub): string
    {
        $paid = $sub->netPaid();

        return match (true) {
            $paid !== [] => collect($paid)->map->format()->implode(' · '),
            $sub->isGrant() => $sub->source->label(),
            default => '—',
        };
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
        ])->title(__('subscriptions.title').' — '.$this->title());
    }

    /** Pull fresh attributes so the header/stats reflect an action taken this request. */
    private function refreshRecord(): void
    {
        if ($fresh = $this->record->fresh(['user', 'plan', 'planPrice', 'previousSubscription', 'grantedBy', 'transactions'])) {
            $this->record = $fresh;
        }
    }
}
