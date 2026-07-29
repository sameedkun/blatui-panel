<?php

namespace App\Livewire\Admin\Management\Subscriptions;

use App\Enum\PaymentProvider;
use App\Enum\SubscriptionStatus;
use App\Livewire\Admin\BaseIndex;
use App\Livewire\Admin\Management\Subscriptions\Concerns\HandlesSubscriptionRowActions;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

#[Layout('layouts.admin.app')]
class Index extends BaseIndex
{
    use HandlesSubscriptionRowActions;

    public string $sortBy = 'starts_at';

    public string $sortDir = 'desc';

    public array $filters = [
        'status' => '',
        'plan_id' => '',
        'provider' => '',
    ];

    protected function baseQuery(): Builder
    {
        // user.activeSubscription is eager loaded too — isLive()/isReactivatable() check it per
        // row (for the actions dropdown), and without this every row would re-query it fresh.
        return Subscription::query()->with(['user.activeSubscription', 'plan', 'planPrice']);
    }

    /** Subscriptions have no direct name/email columns of their own — search reaches into the user and plan relations instead. */
    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->search);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->whereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                ->orWhereHas('plan', fn (Builder $p) => $p->where('name', 'like', "%{$term}%"));
        });
    }

    // ── Filters ───────────────────────────────────────────────────────────────

    protected function filterConfig(): array
    {
        return [
            'status' => [
                'label' => __('subscriptions.fields.status'),
                'type' => 'select',
                'options' => $this->statusOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $q->where('status', $v),
            ],
            'plan_id' => [
                'label' => __('subscriptions.fields.plan'),
                'type' => 'select',
                'options' => $this->planOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $q->where('plan_id', $v),
            ],
            'provider' => [
                'label' => __('subscriptions.fields.provider'),
                'type' => 'select',
                'options' => $this->providerOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $q->where('provider', $v),
            ],
        ];
    }

    protected function filterBarConfig(): array
    {
        return [
            'status' => ['label' => __('subscriptions.fields.status'), 'type' => 'select', 'options' => $this->statusOptions()],
            'plan_id' => ['label' => __('subscriptions.fields.plan'), 'type' => 'select', 'options' => $this->planOptions()],
            'provider' => ['label' => __('subscriptions.fields.provider'), 'type' => 'select', 'options' => $this->providerOptions()],
        ];
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        return collect(SubscriptionStatus::cases())->mapWithKeys(fn (SubscriptionStatus $c) => [$c->value => $c->label()])->all();
    }

    /** @return array<int, string> */
    private function planOptions(): array
    {
        return Plan::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    private function providerOptions(): array
    {
        return collect(PaymentProvider::cases())->mapWithKeys(fn (PaymentProvider $c) => [$c->value => $c->label()])->all();
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    protected function statsConfig(): array
    {
        return [
            [
                'label' => __('subscriptions.stats.total'),
                'value' => fn () => Subscription::count(),
                'icon' => 'receipt',
                'description' => __('subscriptions.stats.all_time_records'),
            ],
            [
                'label' => __('subscriptions.stats.active'),
                'value' => fn () => Subscription::whereIn('status', ['trialing', 'active', 'grace'])->count(),
                'icon' => 'check-circle',
                'description' => __('subscriptions.stats.active_description'),
            ],
            [
                'label' => __('subscriptions.stats.cancelled'),
                'value' => fn () => Subscription::where('status', 'cancelled')->count(),
                'icon' => 'circle-slash',
                'description' => __('subscriptions.stats.cancelled_description'),
            ],
            [
                'label' => __('subscriptions.stats.revenue'),
                'value' => fn () => '$'.number_format((float) Subscription::sum('amount_paid'), 2),
                'icon' => 'banknote',
                'description' => __('subscriptions.stats.revenue_description'),
            ],
        ];
    }

    public function render(): View
    {
        $subscriptions = $this->getRecords();

        return view('livewire.admin.management.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
        ])->title(__('subscriptions.title'));
    }
}
