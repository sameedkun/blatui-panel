<?php

namespace App\Services\Subscription;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityModule;
use App\Enum\CancelledBy;
use App\Enum\PaymentProvider;
use App\Enum\ReceiptType;
use App\Enum\SubscriptionStatus;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionReceipt;
use App\Models\User;
use App\Notifications\Billing\PaymentFailedNotification;
use App\Support\ActivityLogger;
use App\Support\Subscription\ProviderTransaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provider-driven subscription state changes — the third sibling next to
 * {@see SubscriptionService} (user/admin actions) and {@see LifecycleService}
 * (calendar sweep). Every payment-provider webhook integration translates its
 * own events into a {@see ProviderTransaction} and calls one of the methods
 * here, so the state machine, the receipt ledger and the audit trail are
 * identical no matter which store the money came through.
 *
 * Shape of the data:
 *   - one `subscriptions` row per contract (a provider's original transaction),
 *     renewals extend it rather than creating new rows; a plan change starts a
 *     new row linked through `previous_subscription_id`;
 *   - every provider event that touched it is a `subscription_receipts` row
 *     (provider ids + raw transaction + link to the raw webhook notification),
 *     so `subscriptions` never grows provider-specific columns;
 *   - the contract is found again via its receipts' `provider_original_id`.
 *
 * Every method is safe to replay (admin "Reprocess", provider retries):
 * money events are keyed on the provider transaction id, state events no-op
 * when the subscription is already in the target state.
 */
class ProviderSubscriptionService
{
    /** Receipt types that record a charge — at most one per provider transaction id. */
    private const CHARGE_TYPES = [ReceiptType::Initial, ReceiptType::Renewal, ReceiptType::PlanChange];

    public function __construct(private SubscriptionService $subscriptions) {}

    /** The plan price mapped to a provider product id via `plan_price_providers`. */
    public function resolvePrice(PaymentProvider $provider, ?string $productId): ?PlanPrice
    {
        if (! $productId) {
            return null;
        }

        return PlanPrice::query()
            ->whereHas('providers', fn ($query) => $query
                ->where('provider', $provider)
                ->where('external_id', $productId))
            ->with('plan')
            ->first();
    }

    /** The current row of the contract identified by a provider's original transaction id. */
    public function findSubscription(PaymentProvider $provider, ?string $originalTransactionId): ?Subscription
    {
        if (! $originalTransactionId) {
            return null;
        }

        return Subscription::query()
            ->where('provider', $provider)
            ->whereHas('receipts', fn ($query) => $query
                ->where('provider', $provider)
                ->where('provider_original_id', $originalTransactionId))
            ->latest('id')
            ->first();
    }

    /**
     * A new contract (first purchase or resubscribe). Replaces any other live
     * subscription the user has, same rule as {@see SubscriptionService::subscribe()}.
     */
    public function start(User $user, PlanPrice $price, ProviderTransaction $transaction): Subscription
    {
        return DB::transaction(function () use ($user, $price, $transaction): Subscription {
            if ($recorded = $this->recordedCharge($transaction)) {
                return $recorded->subscription;
            }

            $previous = $this->findSubscription($transaction->provider, $transaction->originalTransactionId);

            if ($active = $user->activeSubscription()->first()) {
                $this->subscriptions->cancelSubscription(
                    $active,
                    CancelledBy::System,
                    'Replaced by '.$transaction->provider->label().' subscription',
                    immediately: true,
                    causer: null,
                    context: ActivityContext::Webhook,
                );
            }

            $subscription = $user->subscriptions()->create([
                ...$this->periodAttributes($price, $transaction),
                'provider' => $transaction->provider,
                'previous_subscription_id' => $previous?->id,
            ]);

            $this->recordReceipt($subscription, ReceiptType::Initial, $transaction);

            $this->log($subscription, ActivityAction::Assigned, 'subscription_assigned', [
                'amount' => $subscription->amount_paid,
                'currency' => $subscription->currency,
                'trial' => $transaction->isTrial,
            ]);

            return $subscription;
        });
    }

    /** A successful renewal charge — extends the contract's period. */
    public function renew(Subscription $subscription, ProviderTransaction $transaction, bool $recovered = false): Subscription
    {
        return DB::transaction(function () use ($subscription, $transaction, $recovered): Subscription {
            if ($this->recordedCharge($transaction)) {
                return $subscription;
            }

            $wasTrial = $subscription->status === SubscriptionStatus::Trialing;
            $data = ['grace_ends_at' => null];

            if ($transaction->expiresAt && (! $subscription->ends_at || $transaction->expiresAt->gt($subscription->ends_at))) {
                $data['ends_at'] = $transaction->expiresAt;
            }

            // A contract the user cancelled stays cancelled — a charge landing after
            // that is an out-of-order delivery, and the period it paid for still counts.
            if (! $this->isUserCancelled($subscription)) {
                $data += [
                    'status' => SubscriptionStatus::Active,
                    'is_recurring' => $transaction->autoRenews ?? true,
                    'cancelled_by' => null,
                    'cancelled_reason' => null,
                ];
            }

            $subscription->update($data + $this->adjustedAmountPaid($subscription, $transaction, 1));
            $this->recordReceipt($subscription, ReceiptType::Renewal, $transaction);

            $wasTrial
                ? $this->log($subscription, ActivityAction::Updated, 'subscription_trial_converted')
                : $this->log($subscription, ActivityAction::Updated, 'subscription_renewed', [
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'recovered' => $recovered,
                    'access_until' => $subscription->ends_at?->toIso8601String(),
                ]);

            return $subscription;
        });
    }

    /**
     * The contract moved to a different product (upgrade/downgrade/crossgrade
     * that has taken effect). Mirrors {@see SubscriptionService::upgrade()}:
     * the old row is closed and a new one is chained to it.
     */
    public function changePlan(Subscription $subscription, PlanPrice $price, ProviderTransaction $transaction): Subscription
    {
        return DB::transaction(function () use ($subscription, $price, $transaction): Subscription {
            if ($recorded = $this->recordedCharge($transaction)) {
                return $recorded->subscription;
            }

            $fromPlan = $subscription->plan->name;

            $new = $subscription->user()->withTrashed()->firstOrFail()->subscriptions()->create([
                ...$this->periodAttributes($price, $transaction),
                'starts_at' => $transaction->purchasedAt ?? now(),
                'provider' => $transaction->provider,
                'previous_subscription_id' => $subscription->id,
                'proration_meta' => ['from_plan' => $subscription->plan->slug, 'prorated_by' => $transaction->provider->value],
            ]);

            $subscription->update([
                'status' => SubscriptionStatus::Cancelled,
                'ends_at' => $subscription->ends_at?->isPast() ? $subscription->ends_at : now(),
                'grace_ends_at' => null,
                'is_recurring' => false,
                'cancelled_by' => CancelledBy::System,
                'cancelled_reason' => 'Plan changed to: '.$price->plan->slug,
            ]);

            $this->recordReceipt($new, ReceiptType::PlanChange, $transaction);

            $this->log($new, ActivityAction::Assigned, 'subscription_upgraded', [
                'from_plan' => $fromPlan,
                'to_plan' => $price->plan->name,
                'credit_applied' => 0,
                'amount_charged' => $new->amount_paid,
                'currency' => $new->currency,
            ]);

            return $new;
        });
    }

    /** The user turned auto-renew off — access continues until the paid period ends. */
    public function disableAutoRenew(Subscription $subscription, ProviderTransaction $transaction): Subscription
    {
        if (in_array($subscription->status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired], true)) {
            $subscription->update(['is_recurring' => false]);

            return $subscription;
        }

        return DB::transaction(function () use ($subscription, $transaction): Subscription {
            $subscription->update([
                'status' => SubscriptionStatus::Cancelled,
                'is_recurring' => false,
                'cancelled_by' => CancelledBy::User,
                'cancelled_reason' => 'Auto-renewal turned off',
            ]);

            $this->recordReceipt($subscription, ReceiptType::Cancellation, $transaction);

            $this->log($subscription, ActivityAction::Cancelled, 'subscription_cancelled', [
                'cancelled_by' => CancelledBy::User->value,
                'reason' => 'Auto-renewal turned off',
                'immediately' => false,
                'access_until' => $subscription->accessEndsAt()?->toIso8601String(),
            ]);

            return $subscription;
        });
    }

    /** The user turned auto-renew back on before the period ran out. */
    public function enableAutoRenew(Subscription $subscription, ProviderTransaction $transaction): Subscription
    {
        if (! $this->isUserCancelled($subscription) || ! $subscription->accessEndsAt()?->isFuture()) {
            $subscription->update(['is_recurring' => true]);

            return $subscription;
        }

        return DB::transaction(function () use ($subscription, $transaction): Subscription {
            $subscription->update([
                'status' => $subscription->isOnTrial() ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'is_recurring' => true,
                'cancelled_by' => null,
                'cancelled_reason' => null,
            ]);

            $this->recordReceipt($subscription, ReceiptType::Reactivation, $transaction);
            $this->log($subscription, ActivityAction::Updated, 'subscription_reactivated');

            return $subscription;
        });
    }

    /**
     * A renewal charge failed. With a future `$graceEndsAt` the provider keeps
     * access open while it retries (status Grace); without one, access stops
     * now (status Failed) even though the provider may still recover it.
     */
    public function paymentFailed(Subscription $subscription, ?CarbonInterface $graceEndsAt, ProviderTransaction $transaction): Subscription
    {
        $inGrace = (bool) $graceEndsAt?->isFuture();
        $status = $inGrace ? SubscriptionStatus::Grace : SubscriptionStatus::Failed;

        if ($subscription->status === $status && (! $inGrace || $subscription->grace_ends_at?->equalTo($graceEndsAt))) {
            return $subscription;
        }

        return DB::transaction(function () use ($subscription, $graceEndsAt, $inGrace, $status, $transaction): Subscription {
            $subscription->update([
                'status' => $status,
                'grace_ends_at' => $inGrace
                    ? $graceEndsAt
                    : ($subscription->grace_ends_at?->isFuture() ? now() : $subscription->grace_ends_at),
            ]);

            $this->recordReceipt($subscription, ReceiptType::BillingFailure, $transaction);

            $inGrace
                ? $this->log($subscription, ActivityAction::Updated, 'subscription_entered_grace')
                : $this->log($subscription, ActivityAction::Failed, 'subscription_payment_failed');

            $this->notifyPaymentFailed($subscription, $inGrace ? $graceEndsAt : null);

            return $subscription;
        });
    }

    /**
     * Emails the subscriber once per state change (a replay no-ops before
     * reaching here). Only real app users: a guest's address is a generated
     * placeholder, and a deleted account shouldn't receive billing mail.
     * Queued after commit so a rolled-back change never sends anything.
     */
    private function notifyPaymentFailed(Subscription $subscription, ?CarbonInterface $graceEndsAt): void
    {
        $user = $subscription->user;

        if (! $user?->isAppUser()) {
            return;
        }

        $user->notify((new PaymentFailedNotification($subscription->plan->name, $subscription->provider, $graceEndsAt))->afterCommit());
    }

    /**
     * The contract ended for good. `$reason` is a snake_case code (e.g.
     * `voluntary`, `billing_retry_ended`) — the same shape
     * {@see LifecycleService} logs on `subscription_expired`.
     */
    public function expire(Subscription $subscription, ProviderTransaction $transaction, string $reason, CancelledBy $cancelledBy = CancelledBy::System): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Expired) {
            return $subscription;
        }

        return DB::transaction(function () use ($subscription, $transaction, $reason, $cancelledBy): Subscription {
            $endsAt = match (true) {
                (bool) $subscription->ends_at?->isPast() => $subscription->ends_at,
                (bool) $transaction->expiresAt?->isPast() => $transaction->expiresAt,
                default => now(),
            };

            $subscription->update([
                'status' => SubscriptionStatus::Expired,
                'ends_at' => $endsAt,
                'grace_ends_at' => $subscription->grace_ends_at?->isFuture() ? now() : $subscription->grace_ends_at,
                'is_recurring' => false,
                'cancelled_by' => $subscription->cancelled_by ?? $cancelledBy,
                'cancelled_reason' => $subscription->cancelled_reason ?? Str::headline($reason),
            ]);

            $this->recordReceipt($subscription, ReceiptType::Expiration, $transaction);
            $this->log($subscription, ActivityAction::Updated, 'subscription_expired', ['reason' => $reason]);

            return $subscription;
        });
    }

    /**
     * The provider refunded a charge. Access is only cut when the refunded
     * charge paid for the current period — refunding an older renewal just
     * goes on the ledger.
     */
    public function refund(Subscription $subscription, ProviderTransaction $transaction, ?CarbonInterface $revokedAt = null, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $transaction, $revokedAt, $reason): Subscription {
            if ($this->recorded($transaction, [ReceiptType::Refund])) {
                return $subscription;
            }

            $revokesAccess = ! $transaction->expiresAt
                || ! $subscription->ends_at
                || $transaction->expiresAt->greaterThanOrEqualTo($subscription->ends_at);

            if ($revokesAccess) {
                $subscription->update([
                    'status' => SubscriptionStatus::Cancelled,
                    'ends_at' => $revokedAt ?? now(),
                    'grace_ends_at' => null,
                    'is_recurring' => false,
                    'cancelled_by' => CancelledBy::System,
                    'cancelled_reason' => $reason ? "Refunded: {$reason}" : 'Refunded',
                ]);
            }

            $this->netAgainstCharge($subscription, $transaction, -1);
            $this->recordReceipt($subscription, ReceiptType::Refund, $transaction);

            $this->log($subscription, ActivityAction::Updated, 'subscription_refunded', [
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'reason' => $reason,
                'access_revoked' => $revokesAccess,
            ]);

            return $subscription;
        });
    }

    /** The provider reversed a refund — restore access if that refund had cut it. */
    public function reverseRefund(Subscription $subscription, ProviderTransaction $transaction): Subscription
    {
        return DB::transaction(function () use ($subscription, $transaction): Subscription {
            if ($this->recorded($transaction, [ReceiptType::RefundReversed])) {
                return $subscription;
            }

            $wasRefunded = $this->recorded($transaction, [ReceiptType::Refund]) !== null;
            $restores = $wasRefunded
                && $subscription->status === SubscriptionStatus::Cancelled
                && $subscription->cancelled_by === CancelledBy::System
                && $transaction->expiresAt?->isFuture();

            if ($wasRefunded) {
                $this->netAgainstCharge($subscription, $transaction, 1);
            }

            if ($restores) {
                $subscription->update([
                    'status' => SubscriptionStatus::Active,
                    'ends_at' => $transaction->expiresAt,
                    'is_recurring' => $transaction->autoRenews ?? false,
                    'cancelled_by' => null,
                    'cancelled_reason' => null,
                ]);

                $this->log($subscription, ActivityAction::Updated, 'subscription_reactivated', ['reason' => 'refund_reversed']);
            }

            $this->recordReceipt($subscription, ReceiptType::RefundReversed, $transaction);

            return $subscription;
        });
    }

    /** The provider pushed the renewal date out (e.g. a goodwill extension). */
    public function extend(Subscription $subscription, ProviderTransaction $transaction): Subscription
    {
        if (! $transaction->expiresAt || ($subscription->ends_at && $transaction->expiresAt->lessThanOrEqualTo($subscription->ends_at))) {
            return $subscription;
        }

        return DB::transaction(function () use ($subscription, $transaction): Subscription {
            $from = $subscription->ends_at;

            $subscription->update(['ends_at' => $transaction->expiresAt]);
            $this->recordReceipt($subscription, ReceiptType::Extension, $transaction);

            $this->log($subscription, ActivityAction::Updated, 'subscription_extended', [
                'from' => $from?->toIso8601String(),
                'access_until' => $transaction->expiresAt->toIso8601String(),
            ]);

            return $subscription;
        });
    }

    /** Access was withdrawn by the provider (e.g. Family Sharing removed) — ends now. */
    public function revoke(Subscription $subscription, ProviderTransaction $transaction, string $reason): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Cancelled && ! $subscription->accessEndsAt()?->isFuture()) {
            return $subscription;
        }

        return DB::transaction(function () use ($subscription, $transaction, $reason): Subscription {
            $subscription->update([
                'status' => SubscriptionStatus::Cancelled,
                'ends_at' => now(),
                'grace_ends_at' => null,
                'is_recurring' => false,
                'cancelled_by' => CancelledBy::System,
                'cancelled_reason' => $reason,
            ]);

            $this->recordReceipt($subscription, ReceiptType::Revocation, $transaction);

            $this->log($subscription, ActivityAction::Cancelled, 'subscription_cancelled', [
                'cancelled_by' => CancelledBy::System->value,
                'reason' => $reason,
                'immediately' => true,
                'access_until' => $subscription->ends_at?->toIso8601String(),
            ]);

            return $subscription;
        });
    }

    /**
     * Period/price columns for a new contract row, taken from the provider's
     * own dates rather than computed from the plan price — the store owns the
     * billing calendar.
     *
     * @return array<model-property<Subscription>, mixed>
     */
    private function periodAttributes(PlanPrice $price, ProviderTransaction $transaction): array
    {
        $status = match (true) {
            (bool) $transaction->expiresAt?->isPast() => SubscriptionStatus::Expired,
            $transaction->isTrial => SubscriptionStatus::Trialing,
            default => SubscriptionStatus::Active,
        };

        return [
            'plan_id' => $price->plan_id,
            'plan_price_id' => $price->id,
            'starts_at' => $transaction->purchasedAt ?? now(),
            'trial_ends_at' => $transaction->isTrial ? $transaction->expiresAt : null,
            'ends_at' => $transaction->expiresAt,
            'grace_ends_at' => null,
            'amount_paid' => $transaction->amount ?? ($transaction->isTrial ? 0 : $price->amount),
            'currency' => $transaction->currency ?? $price->currency,
            'status' => $status,
            'is_recurring' => $transaction->autoRenews ?? true,
        ];
    }

    /**
     * `amount_paid` is the running net total a contract row has collected —
     * opening charge + renewals − refunds — which is what the panel's "Total
     * amount paid" / "Revenue collected" / plan revenue figures read. `$sign`
     * is +1 for a charge, −1 for a refund. A charge in a different currency
     * than the row (e.g. the customer moved storefront) isn't summed into it;
     * it stays on its receipt only.
     *
     * @return array<model-property<Subscription>, mixed>
     */
    private function adjustedAmountPaid(Subscription $subscription, ProviderTransaction $transaction, int $sign): array
    {
        if ($transaction->amount === null || ($transaction->currency && $transaction->currency !== $subscription->currency)) {
            return [];
        }

        return ['amount_paid' => max(0, round((float) $subscription->amount_paid + $sign * (float) $transaction->amount, 2))];
    }

    /** Applies a refund (or its reversal) to the row whose receipt holds the original charge. */
    private function netAgainstCharge(Subscription $subscription, ProviderTransaction $transaction, int $sign): void
    {
        $charged = $this->recordedCharge($transaction)?->subscription;
        $target = $charged && ! $charged->is($subscription) ? $charged : $subscription;

        $target->update($this->adjustedAmountPaid($target, $transaction, $sign));
    }

    private function isUserCancelled(Subscription $subscription): bool
    {
        return $subscription->status === SubscriptionStatus::Cancelled
            && $subscription->cancelled_by === CancelledBy::User;
    }

    private function recordedCharge(ProviderTransaction $transaction): ?SubscriptionReceipt
    {
        return $this->recorded($transaction, self::CHARGE_TYPES);
    }

    /** @param  list<ReceiptType>  $types */
    private function recorded(ProviderTransaction $transaction, array $types): ?SubscriptionReceipt
    {
        if (! $transaction->transactionId) {
            return null;
        }

        return SubscriptionReceipt::query()
            ->where('provider', $transaction->provider)
            ->where('provider_transaction_id', $transaction->transactionId)
            ->whereIn('type', $types)
            ->with('subscription')
            ->first();
    }

    private function recordReceipt(Subscription $subscription, ReceiptType $type, ProviderTransaction $transaction): SubscriptionReceipt
    {
        return $subscription->receipts()->create([
            'provider' => $transaction->provider,
            'type' => $type,
            'provider_transaction_id' => $transaction->transactionId,
            'provider_original_id' => $transaction->originalTransactionId,
            'payload' => [
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'expires_at' => $transaction->expiresAt?->toIso8601String(),
                'transaction' => $transaction->payload,
            ],
            'notification_provider' => $transaction->notification ? $transaction->provider : null,
            'notification_id' => $transaction->notification?->getKey(),
        ]);
    }

    /** @param  array<string, mixed>  $properties */
    private function log(Subscription $subscription, ActivityAction $action, string $type, array $properties = []): void
    {
        ActivityLogger::log(
            ActivityModule::User,
            $action,
            $subscription->user()->withTrashed()->first(),
            [
                'type' => $type,
                'plan' => $subscription->plan->name,
                'provider' => $subscription->provider->value,
                ...$properties,
            ],
            causer: null,
            context: ActivityContext::Webhook,
        );
    }
}
