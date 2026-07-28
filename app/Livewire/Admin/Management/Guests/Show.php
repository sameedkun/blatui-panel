<?php

namespace App\Livewire\Admin\Management\Guests;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\CancelledBy;
use App\Livewire\Admin\BaseShow;
use App\Livewire\Admin\Concerns\HasActivityDetailModal;
use App\Livewire\Admin\Concerns\HasShowTabs;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Management\Guests\Concerns\HandlesGuestRowActions;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\GuestConversionService;
use App\Services\SubscriptionService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-focused profile page for a single guest — the trimmed counterpart to
 * {@see \App\Livewire\Admin\Management\Users\Show}. Guests never enter the
 * app-user grace-period deletion flow or email-verification lifecycle, so
 * both the tab set and the header actions are deliberately smaller here.
 *
 * Ban/delete/restore reuse the shared {@see HandlesGuestRowActions} — a ban
 * here is byte-for-byte the same action (and audit-log row) as a ban from the
 * index. Convert/merge are profile-only (no Guests index row action for
 * either), so they're defined directly on this class instead of that trait.
 */
#[Layout('layouts.admin.app')]
class Show extends BaseShow
{
    use HandlesGuestRowActions;
    use HasActivityDetailModal;
    use HasShowTabs;
    use LogsAdminActivity;
    use WithPagination;

    // Convert/merge are only offered from this profile page (not the Guests
    // index row actions), so — unlike ban/delete/restore — they live here
    // directly rather than in the shared HandlesGuestRowActions trait.

    public ?int $convertingId = null;

    public string $convertEmail = '';

    public string $convertName = '';

    public bool $convertMarkEmailVerified = false;

    public ?int $mergingId = null;

    public ?int $mergeDestinationId = null;

    public string $mergeSearch = '';

    public string $mergeReason = '';

    /** Plan/price picked in the "Assign / Change Plan" dialog. */
    public ?int $assignPlanId = null;

    public ?int $assignPriceId = null;

    /** Shared reason field for the two cancel dialogs (only one is open at a time). */
    public string $cancelReason = '';

    public function mount(User $user): void
    {
        abort_unless($user->isGuest(), 404);

        $this->initShow($user);
    }

    protected function indexRoute(): string
    {
        return 'admin.guests.index';
    }

    protected function title(): string
    {
        return $this->record->name;
    }

    /**
     * No Devices/Tickets here — guests don't have them. Subscriptions reuses
     * the exact same partial and management actions as the Users profile
     * (a guest is still just a row in the same `users` table, so it can be
     * assigned/upgraded/cancelled/reactivated identically); Activity does too.
     */
    protected function tabs(): array
    {
        return [
            'overview' => [
                'label' => __('guests.tabs.overview'),
                'icon' => 'user',
                'view' => 'livewire.admin.management.guests.profile.tabs.overview',
            ],
            'subscriptions' => [
                'label' => __('guests.tabs.subscriptions'),
                'icon' => 'credit-card',
                'view' => 'livewire.admin.management.users.profile.tabs.subscriptions',
                'data' => fn (): array => [
                    'subscriptionHistory' => $this->subscriptionHistory(),
                    'reactivatable' => $this->reactivatableSubscription(),
                ],
            ],
            'activity' => [
                'label' => __('guests.tabs.activity'),
                'icon' => 'activity',
                'view' => 'livewire.admin.management.users.profile.tabs.activity',
                'permission' => 'activity_logs.view',
                'data' => fn (): array => [
                    'activities' => $this->recordActivity(),
                    'selectedActivity' => $this->selectedActivityDetail(),
                ],
            ],
        ];
    }

    /** Paginated audit trail for this record — powers the profile's Activity tab. */
    protected function recordActivity(): LengthAwarePaginator
    {
        return Activity::forSubject($this->record)
            ->with('causer')
            ->latest()
            ->paginate(10);
    }

    // -------------------------------------------------------------------------
    // Subscription management — identical to Users/Show; a guest is still just
    // a `users` row, so it can hold and be managed on a subscription the same way.
    // -------------------------------------------------------------------------

    /** Every subscription this account has ever had, newest first — powers the Subscriptions tab history table. */
    protected function subscriptionHistory(): LengthAwarePaginator
    {
        return $this->record->subscriptions()
            ->with(['plan', 'planPrice'])
            ->latest('starts_at')
            ->paginate(10, pageName: 'subs_page');
    }

    /** A cancelled-but-still-in-period subscription that can be undone, if any. */
    protected function reactivatableSubscription(): ?Subscription
    {
        return $this->record->subscriptions()
            ->where('status', 'cancelled')
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();
    }

    /** [plan id => name] for the active, purchasable plans offered in the assign/change dialog. */
    protected function planOptions(): array
    {
        return Plan::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all();
    }

    /** [price id => label] for a given plan's active prices. */
    protected function priceOptionsFor(?int $planId): array
    {
        if (! $planId) {
            return [];
        }

        return PlanPrice::query()
            ->where('plan_id', $planId)
            ->where('is_active', true)
            ->orderBy('amount')
            ->get()
            ->mapWithKeys(fn (PlanPrice $price): array => [
                $price->id => "{$price->currency} {$price->amount} / {$price->billing_period} "
                    .$price->billing_interval->label().($price->billing_period > 1 ? 's' : ''),
            ])
            ->all();
    }

    public function openAssignPlanDialog(): void
    {
        $this->authorize('guests.manage');

        $current = $this->record->activeSubscription;
        $this->assignPlanId = $current?->plan_id;
        $this->assignPriceId = $current?->plan_price_id;

        $this->dispatch('open-dialog-assign-plan');
    }

    /** Reset the price when the plan changes so a stale price is never submitted. */
    public function updatedAssignPlanId(): void
    {
        $options = $this->priceOptionsFor($this->assignPlanId);

        if (! isset($options[$this->assignPriceId])) {
            $this->assignPriceId = array_key_first($options);
        }
    }

    public function assignPlan(SubscriptionService $service): void
    {
        $this->authorize('guests.manage');

        $this->validate([
            'assignPlanId' => ['required', 'integer'],
            'assignPriceId' => ['required', 'integer'],
        ]);

        $price = PlanPrice::findOrFail($this->assignPriceId);
        $guest = $this->record;

        $subscription = $guest->activeSubscription
            ? $service->upgrade($guest, $price)
            : $service->subscribe($guest, $price);

        $this->assignPlanId = null;
        $this->assignPriceId = null;

        $this->toastSuccess(__('users.toasts.plan_assigned', ['name' => $guest->name, 'plan' => $subscription->plan->name]));
    }

    public function openCancelImmediatelyDialog(): void
    {
        $this->authorize('guests.manage');

        $this->cancelReason = '';
        $this->dispatch('open-dialog-cancel-immediately');
    }

    public function cancelImmediately(SubscriptionService $service): void
    {
        $this->authorize('guests.manage');

        $guest = $this->record;
        $active = $guest->activeSubscription;

        if (! $active) {
            $this->toastError(__('users.toasts.no_active_subscription'));

            return;
        }

        $planName = $active->plan->name;
        $service->cancelActive($guest, CancelledBy::Admin, trim($this->cancelReason) ?: null, true);

        $this->cancelReason = '';
        $this->toastSuccess(__('users.toasts.subscription_cancelled_immediately', ['plan' => $planName]));
    }

    public function openCancelAtPeriodEndDialog(): void
    {
        $this->authorize('guests.manage');

        $this->cancelReason = '';
        $this->dispatch('open-dialog-cancel-at-period-end');
    }

    public function cancelAtPeriodEnd(SubscriptionService $service): void
    {
        $this->authorize('guests.manage');

        $guest = $this->record;
        $active = $guest->activeSubscription;

        if (! $active) {
            $this->toastError(__('users.toasts.no_active_subscription'));

            return;
        }

        $planName = $active->plan->name;
        $endsAt = $active->ends_at;
        $service->cancelActive($guest, CancelledBy::Admin, trim($this->cancelReason) ?: null, false);

        $this->cancelReason = '';
        $this->toastSuccess(__('users.toasts.subscription_cancelled_period_end', ['plan' => $planName, 'date' => $endsAt?->format('M d, Y')]));
    }

    public function reactivateSubscription(SubscriptionService $service): void
    {
        $this->authorize('guests.manage');

        try {
            $subscription = $service->reactivate($this->record);
            $this->toastSuccess(__('users.toasts.subscription_reactivated', ['plan' => $subscription->plan->name]));
        } catch (InvalidArgumentException $e) {
            $this->toastError($e->getMessage());
        }
    }

    /**
     * Instant delete removes the record, so — unlike the index — the profile
     * has nowhere to stay and returns to the list. The deletion itself (and
     * its audit entry) still runs through {@see AccountDeletionService}; no
     * new logic here.
     */
    public function delete(AccountDeletionService $deletions)
    {
        $this->authorize('guests.delete');

        $guest = User::query()->guests()->withTrashed()->findOrFail($this->deletingId);
        $this->assertLifecycleState($guest, ['active']);

        $name = $guest->name;
        $deletions->purgeGuestByAdmin($guest);

        session()->flash('toast', ['type' => 'success', 'title' => __('guests.toasts.guest_deleted', ['name' => $name])]);

        return $this->redirect(route('admin.guests.index'));
    }

    /**
     * Force-delete also removes the record — same "nowhere to stay" reasoning
     * as {@see delete()} — so the profile redirects back to the list on success.
     */
    public function forceDelete()
    {
        $this->authorize('guests.force-delete');

        $guest = User::query()->guests()->withTrashed()->findOrFail($this->forceDeleteId);
        $this->assertLifecycleState($guest, ['trashed']);

        $name = $guest->name;

        $this->logActivity(ActivityModule::Guest, ActivityAction::ForceDeleted, $guest, ['user_id' => $guest->id, 'name' => $name]);

        $guest->forceDelete();

        session()->flash('toast', ['type' => 'success', 'title' => __('guests.toasts.guest_permanently_deleted', ['name' => $name])]);

        return $this->redirect(route('admin.guests.index'));
    }

    public function openConvertDialog(int $userId): void
    {
        $this->authorize('users.convert');

        $guest = User::query()->guests()->withTrashed()->findOrFail($userId);
        $this->assertLifecycleState($guest, ['active']);

        $this->convertingId = $userId;
        $this->convertEmail = $guest->email;
        $this->convertName = $guest->name;
        $this->convertMarkEmailVerified = false;
        $this->dispatch('open-dialog-convert-user');
    }

    /**
     * The record stops being a guest on conversion, so — unlike the index —
     * the profile has nowhere to stay and redirects to the new app user's
     * page. The conversion itself (and its audit entry) still runs through
     * {@see GuestConversionService}; no new logic here.
     */
    public function confirmConvert(GuestConversionService $conversions)
    {
        $this->authorize('users.convert');

        $guest = User::query()->guests()->withTrashed()->findOrFail($this->convertingId);
        $this->assertLifecycleState($guest, ['active']);

        $this->validate([
            'convertEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($guest->id)],
        ]);

        $name = $guest->name;
        $conversions->convertByAdmin($guest, $this->convertEmail, trim($this->convertName) ?: null, $this->convertMarkEmailVerified);

        session()->flash('toast', ['type' => 'success', 'title' => __('guests.toasts.guest_converted', ['name' => $name])]);

        return $this->redirect(route('admin.users.show', $guest));
    }

    public function openMergeDialog(int $userId): void
    {
        $this->authorize('users.merge');

        $guest = User::query()->guests()->withTrashed()->findOrFail($userId);
        $this->assertLifecycleState($guest, ['active']);

        $this->mergingId = $userId;
        $this->mergeDestinationId = null;
        $this->mergeSearch = '';
        $this->mergeReason = '';
        $this->dispatch('open-dialog-merge-guest');
    }

    public function selectMergeDestination(int $userId): void
    {
        $this->mergeDestinationId = $userId;
        $this->mergeSearch = '';
    }

    public function clearMergeDestination(): void
    {
        $this->mergeDestinationId = null;
    }

    /** @return Collection<int, User> */
    public function mergeCandidates(): Collection
    {
        $term = trim($this->mergeSearch);

        if ($term === '') {
            return collect();
        }

        return User::query()
            ->appUsers()
            ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
            ->limit(8)
            ->get();
    }

    /**
     * The guest row is force-deleted on merge, so — same "nowhere to stay"
     * reasoning as {@see confirmConvert()} — the profile redirects to the
     * surviving destination account's page instead. The merge itself (and
     * its audit entry) still runs through {@see GuestConversionService}.
     */
    public function confirmMerge(GuestConversionService $conversions)
    {
        $this->authorize('users.merge');

        $guest = User::query()->guests()->withTrashed()->findOrFail($this->mergingId);
        $this->assertLifecycleState($guest, ['active']);

        $this->validate([
            'mergeDestinationId' => ['required', 'integer'],
            'mergeReason' => ['required', 'string'],
        ]);

        $destination = User::query()->appUsers()->findOrFail($this->mergeDestinationId);
        $guestName = $guest->name;

        $conversions->mergeByAdmin($guest, $destination, $this->mergeReason);

        session()->flash('toast', ['type' => 'success', 'title' => __('guests.toasts.guest_merged', ['guest' => $guestName, 'destination' => $destination->name])]);

        return $this->redirect(route('admin.users.show', $destination));
    }

    /**
     * Summary cards under the header. Real values come from their own accessor
     * so a future module fills one in without touching the layout; unbuilt
     * metrics return null and render a "Coming soon" placeholder.
     *
     * @return array<int, array{label: string, icon: string, value: string|null}>
     */
    public function statCards(): array
    {
        return [
            ['label' => __('users.fields.plan'), 'icon' => 'credit-card', 'value' => $this->record->activeSubscription?->plan?->name ?? __('users.status_labels.free')],
            ['label' => __('guests.tabs.activity'), 'icon' => 'activity', 'value' => $this->recordActivityCount()],
            ['label' => __('users.fields.registered'), 'icon' => 'calendar', 'value' => $this->record->registration_date?->format('M d, Y') ?? '—'],
        ];
    }

    /** Total audit-log rows for this record, or null (renders "Coming soon") without permission to see them. */
    protected function recordActivityCount(): ?string
    {
        return auth()->user()->can('activity_logs.view')
            ? (string) Activity::forSubject($this->record)->count()
            : null;
    }

    public function render(): View
    {
        // Reflect any header action (ban, delete) on this same request.
        $this->refreshRecord();

        return view('livewire.admin.management.guests.show', [
            'stats' => $this->statCards(),
            'planOptions' => $this->planOptions(),
            'priceOptions' => $this->priceOptionsFor($this->assignPlanId),
        ])->title(__('guests.title').' — '.$this->title());
    }

    /** Pull fresh attributes so header badges reflect an action taken this request. */
    private function refreshRecord(): void
    {
        if ($fresh = $this->record->fresh()) {
            $this->record = $fresh;
        }
    }
}
