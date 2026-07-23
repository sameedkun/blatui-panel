<?php

namespace App\Livewire\Admin\Management\Subscriptions\Concerns;

use App\Enum\SubscriptionStatus;
use App\Livewire\Admin\BaseIndex;
use App\Livewire\Admin\BaseShow;
use App\Livewire\Admin\Concerns\HasToast;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use InvalidArgumentException;

/**
 * Single-subscription lifecycle actions (cancel immediately, cancel at period
 * end, reactivate) shared by the Subscriptions index and the subscription
 * profile page — a cancel from either surface runs byte-for-byte the same
 * code (and writes the same audit row) via {@see SubscriptionService}, which
 * is the only place subscription state actually changes.
 *
 * SubscriptionService's methods are keyed by *user* (they always resolve a
 * user's current subscription themselves), not by a specific subscription
 * row id. {@see isLive()} guards every mutation here so an action can only
 * ever be taken against the row it was opened for — a stale tab or a forged
 * id targeting a historical/replaced subscription is rejected rather than
 * silently acting on whatever the user's subscription happens to be now.
 *
 * Requires the using component to also use {@see HasToast}
 * (both {@see BaseIndex} and {@see BaseShow} already do).
 * The matching dialogs are the shared `subscriptions/partials/dialogs.blade.php`.
 */
trait HandlesSubscriptionRowActions
{
    /** The subscription targeted by an open cancel dialog. */
    public ?int $targetSubscriptionId = null;

    /** Shared reason field for the two cancel dialogs (only one is open at a time). */
    public string $cancelReason = '';

    /** Whether this row is the user's current live subscription (only live rows can be acted on). */
    public function isLive(Subscription $subscription): bool
    {
        return $subscription->user !== null
            && $subscription->id === $subscription->user->activeSubscription?->id;
    }

    /** Whether this row is a cancelled-but-still-in-period subscription that can be undone. */
    public function isReactivatable(Subscription $subscription): bool
    {
        return $subscription->status === SubscriptionStatus::Cancelled
            && $subscription->ends_at !== null
            && $subscription->ends_at->isFuture();
    }

    public function openCancelImmediatelyDialog(int $subscriptionId): void
    {
        $this->authorize('subscriptions.manage');

        $this->targetSubscriptionId = $subscriptionId;
        $this->cancelReason = '';
        $this->dispatch('open-dialog-cancel-immediately');
    }

    public function cancelImmediately(SubscriptionService $service): void
    {
        $this->authorize('subscriptions.manage');

        $subscription = Subscription::with(['user', 'plan'])->findOrFail($this->targetSubscriptionId);

        if (! $this->isLive($subscription)) {
            $this->targetSubscriptionId = null;
            $this->toastError('This is no longer the active subscription for this user.');

            return;
        }

        $planName = $subscription->plan->name;
        $service->cancelActive($subscription->user, 'admin', trim($this->cancelReason) ?: null, true);

        $this->targetSubscriptionId = null;
        $this->cancelReason = '';
        $this->toastSuccess("{$planName} subscription cancelled immediately.");
    }

    public function openCancelAtPeriodEndDialog(int $subscriptionId): void
    {
        $this->authorize('subscriptions.manage');

        $this->targetSubscriptionId = $subscriptionId;
        $this->cancelReason = '';
        $this->dispatch('open-dialog-cancel-at-period-end');
    }

    public function cancelAtPeriodEnd(SubscriptionService $service): void
    {
        $this->authorize('subscriptions.manage');

        $subscription = Subscription::with(['user', 'plan'])->findOrFail($this->targetSubscriptionId);

        if (! $this->isLive($subscription)) {
            $this->targetSubscriptionId = null;
            $this->toastError('This is no longer the active subscription for this user.');

            return;
        }

        $planName = $subscription->plan->name;
        $service->cancelActive($subscription->user, 'admin', trim($this->cancelReason) ?: null, false);
        $endsAt = $subscription->fresh()->ends_at;

        $this->targetSubscriptionId = null;
        $this->cancelReason = '';
        $this->toastSuccess("{$planName} subscription will end on ".$endsAt?->format('M d, Y').'.');
    }

    public function reactivateRow(int $subscriptionId, SubscriptionService $service): void
    {
        $this->authorize('subscriptions.manage');

        $subscription = Subscription::with('user')->findOrFail($subscriptionId);

        if (! $this->isReactivatable($subscription)) {
            $this->toastError('This subscription can no longer be reactivated.');

            return;
        }

        try {
            $reactivated = $service->reactivate($subscription->user);
            $this->toastSuccess("{$reactivated->plan->name} subscription reactivated.");
        } catch (InvalidArgumentException $e) {
            $this->toastError($e->getMessage());
        }
    }
}
