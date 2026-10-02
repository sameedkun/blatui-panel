<?php

namespace App\Services\Subscription;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityModule;
use App\Enum\CancelledBy;
use App\Enum\PaymentProvider;
use App\Enum\SubscriptionSource;
use App\Enum\SubscriptionStatus;
use App\Enum\TransactionType;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Notifications\Billing\PaymentFailedNotification;
use App\Support\ActivityLogger;
use App\Support\Money\Money;
use App\Support\Subscription\ProviderTransaction;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;

/**
 * Provider-driven subscription state changes — the third sibling next to
 * {@see SubscriptionService} (user/admin actions) and {@see LifecycleService}
 * (calendar sweep). Every payment-provider webhook integration translates its
 * own events into a {@see ProviderTransaction} and calls one of the methods
 * here, so the state machine, the financial ledger and the audit trail are
 * identical no matter which store the money came through.
 *
 * Shape of the data:
 *   - one `subscriptions` row per contract (a provider's original transaction),
 *     renewals extend it rather than creating new rows; a plan change starts a
 *     new row linked through `previous_subscription_id`. The row is the
 *     entitlement and carries no money;
 *   - every charge, renewal, refund and refund reversal is a
 *     `subscription_transactions` row with its own amount + currency (already
 *     normalised by the integration), provider ids, the raw transaction and a
 *     link to the raw webhook notification. State-only events (auto-renew
 *     toggled, billing failed, expired, ...) change the subscription and the
 *     audit log, not the ledger;
 *   - the contract is found again via its transactions' `provider_original_id`
 *     — every contract opens with an Initial or PlanChange transaction.
 *
 * Every method is safe to replay (admin "Reprocess", provider retries) and to
 * run concurrently. Each money movement carries an `idempotency_key` that is
 * unique per provider in the database (see {@see SubscriptionTransaction}):
 * the "already recorded?" check below is only the fast path — if two workers
 * race past it, the second insert violates the unique index, its whole
 * database transaction (subscription changes included) rolls back, and the
 * call resolves to what the winner recorded. Keys come from the provider
 * transaction id (never a notification id — one transaction can be named by
 * several notifications). State-only events no-op when the subscription is
 * already in the target state.
 */
class ProviderSubscriptionService
{
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
            ->whereHas('transactions', fn ($query) => $query
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
        return $this->idempotently(function () use ($user, $price, $transaction): Subscription {
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

            $this->recordTransaction($subscription, TransactionType::Initial, $transaction, $this->chargeKey($transaction));

            $this->log($subscription, ActivityAction::Assigned, 'subscription_assigned', [
                ...$this->moneyProperties($transaction->amount),
                'trial' => $transaction->isTrial,
            ]);

            return $subscription;
        }, fn (): Subscription => $this->winningCharge($transaction)->subscription);
    }

    /** A successful renewal charge — extends the contract's period. */
    public function renew(Subscription $subscription, ProviderTransaction $transaction, bool $recovered = false): Subscription
    {
        return $this->idempotently(function () use ($subscription, $transaction, $recovered): Subscription {
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

            $subscription->update($data);
            $this->recordTransaction($subscription, TransactionType::Renewal, $transaction, $this->chargeKey($transaction));
            $this->flagOwnerlessCharge($subscription, $transaction);

            $wasTrial
                ? $this->log($subscription, ActivityAction::Updated, 'subscription_trial_converted')
                : $this->log($subscription, ActivityAction::Updated, 'subscription_renewed', [
                    ...$this->moneyProperties($transaction->amount),
                    'recovered' => $recovered,
                    'access_until' => $subscription->ends_at?->toIso8601String(),
                ]);

            return $subscription;
        }, fn (): Subscription => $subscription->refresh());
    }

    /**
     * The contract moved to a different product (upgrade/downgrade/crossgrade
     * that has taken effect). Mirrors {@see SubscriptionService::upgrade()}:
     * the old row is closed and a new one is chained to it.
     */
    public function changePlan(Subscription $subscription, PlanPrice $price, ProviderTransaction $transaction): Subscription
    {
        return $this->idempotently(function () use ($subscription, $price, $transaction): Subscription {
            if ($recorded = $this->recordedCharge($transaction)) {
                return $recorded->subscription;
            }

            $fromPlan = $subscription->plan->name;

            // Same owner as the contract — or none, if its account was purged.
            $new = Subscription::query()->create([
                'user_id' => $subscription->user_id,
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

            $this->recordTransaction($new, TransactionType::PlanChange, $transaction, $this->chargeKey($transaction));
            $this->flagOwnerlessCharge($new, $transaction);

            $this->log($new, ActivityAction::Assigned, 'subscription_upgraded', [
                'from_plan' => $fromPlan,
                'to_plan' => $price->plan->name,
                'amount_charged' => $transaction->amount?->toDecimal(),
                'currency' => $transaction->amount?->currency,
            ]);

            return $new;
        }, fn (): Subscription => $this->winningCharge($transaction)->subscription);
    }

    /** The user turned auto-renew off — access continues until the paid period ends. */
    public function disableAutoRenew(Subscription $subscription, ProviderTransaction $transaction): Subscription
    {
        if (in_array($subscription->status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired], true)) {
            $subscription->update(['is_recurring' => false]);

            return $subscription;
        }

        return DB::transaction(function () use ($subscription): Subscription {
            $subscription->update([
                'status' => SubscriptionStatus::Cancelled,
                'is_recurring' => false,
                'cancelled_by' => CancelledBy::User,
                'cancelled_reason' => 'Auto-renewal turned off',
            ]);

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

        return DB::transaction(function () use ($subscription): Subscription {
            $subscription->update([
                'status' => $subscription->isOnTrial() ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'is_recurring' => true,
                'cancelled_by' => null,
                'cancelled_reason' => null,
            ]);
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

        return DB::transaction(function () use ($subscription, $graceEndsAt, $inGrace, $status): Subscription {
            $subscription->update([
                'status' => $status,
                'grace_ends_at' => $inGrace
                    ? $graceEndsAt
                    : ($subscription->grace_ends_at?->isFuture() ? now() : $subscription->grace_ends_at),
            ]);

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
            $this->log($subscription, ActivityAction::Updated, 'subscription_expired', ['reason' => $reason]);

            return $subscription;
        });
    }

    /**
     * The provider refunded (part of) a charge. `$transaction->amount` is the
     * **total** refunded on that provider transaction so far, as the provider
     * reports it; `$transaction->eventKey` identifies this refund event. Only
     * the part not already on the ledger is recorded, so:
     *   - a redelivered/replayed refund event records nothing (same key);
     *   - a second partial refund records just the additional amount;
     *   - an older partial refund arriving after a newer one records nothing;
     *   - a refund after a reversal is recorded in full again;
     *   - the total refunded never exceeds the recorded charge.
     *
     * The refund goes on the ledger of the row that holds the original charge
     * (in a plan-change chain that may be an earlier row) and points at that
     * charge. Access is only cut when the refunded charge paid for the current
     * period — refunding an older renewal just goes on the ledger.
     */
    public function refund(Subscription $subscription, ProviderTransaction $transaction, ?CarbonInterface $revokedAt = null, ?string $reason = null): Subscription
    {
        $key = $this->refundKey($transaction);

        return $this->idempotently(function () use ($subscription, $transaction, $revokedAt, $reason, $key): Subscription {
            if ($key && $this->findRecorded($transaction->provider, $key)) {
                return $subscription;
            }

            // Locks the charge row so refunds/reversals of one transaction are applied one at a time.
            $charge = $this->recordedCharge($transaction, lock: true);
            $refunded = $this->unrecordedRefund($transaction, $charge);

            if ($refunded !== null && $refunded->minor <= 0) {
                if ($refunded->minor < 0) {
                    $this->logLowerRefundTotal($transaction, $refunded);
                }

                return $subscription;
            }

            // Only a refund of the charge that pays for the *current* row's period can
            // cut access. A charge owned by a superseded row (e.g. the plan before an
            // upgrade) is refunded on the ledger alone — its period may well outlast the
            // new row's, but the customer is paying for the new row now.
            $owner = $charge->subscription ?? $subscription;
            $revokesAccess = $owner->is($subscription)
                && (! $transaction->expiresAt
                    || ! $subscription->ends_at
                    || $transaction->expiresAt->greaterThanOrEqualTo($subscription->ends_at));

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

            $this->recordTransaction(
                $owner,
                TransactionType::Refund,
                $transaction->withAmount($refunded),
                $key,
                at: $revokedAt ?? now(),
                related: $charge,
            );

            $this->log($subscription, ActivityAction::Updated, 'subscription_refunded', [
                ...$this->moneyProperties($refunded),
                'refunded_total' => $transaction->amount?->toDecimal(),
                'reason' => $reason,
                'access_revoked' => $revokesAccess,
            ]);

            return $subscription;
        }, fn (): Subscription => $subscription->refresh());
    }

    /**
     * Whether a refund recorded on this provider transaction is still standing
     * (not reversed) — lets an integration flag provider events that
     * contradict the ledger, such as a refund being declined after it was paid.
     */
    public function hasUnreversedRefund(PaymentProvider $provider, string $transactionId): bool
    {
        return SubscriptionTransaction::query()
            ->where('provider', $provider)
            ->where('provider_transaction_id', $transactionId)
            ->where('type', TransactionType::Refund)
            ->whereDoesntHave('reversals')
            ->exists();
    }

    /**
     * The provider reported a cumulative refunded total *lower* than what the
     * ledger already holds, with no reversal in between. Either an older event
     * arrived late (harmless — nothing is recorded), or the provider lowered
     * the refund some other way, which would leave the ledger overstating
     * refunds. The two are indistinguishable here, so it is logged for review.
     */
    private function logLowerRefundTotal(ProviderTransaction $transaction, Money $difference): void
    {
        $reported = $transaction->amount;

        Log::channel('webhooks')->warning('Refund total lower than already recorded — ledger left unchanged, review if this was not a late delivery', [
            'provider' => $transaction->provider->value,
            'transaction_id' => $transaction->transactionId,
            'original_transaction_id' => $transaction->originalTransactionId,
            'event_key' => $transaction->eventKey,
            'reported_total' => $reported?->toDecimal(),
            'recorded_total' => $reported?->minus($difference)->toDecimal(),
            'currency' => $reported?->currency,
            'notification_id' => $transaction->notification?->getKey(),
        ]);
    }

    /**
     * The provider reversed its refund(s) on a transaction — the money is ours
     * again. Every refund on that transaction issued at or before
     * `$reversedAt` and not yet reversed gets exactly one reversal of exactly
     * its amount, pointing at it; a refund issued after the reversal is left
     * alone. Restores access if the refund had cut it.
     *
     * Returns false when there is no recorded refund to reverse (the refund
     * hasn't been processed yet, or predates the integration) so the caller
     * can leave the event for a later retry; true once applied, including
     * when replayed.
     */
    public function reverseRefund(Subscription $subscription, ProviderTransaction $transaction, ?CarbonInterface $reversedAt = null): bool
    {
        if (! $transaction->transactionId) {
            return false;
        }

        return $this->idempotently(function () use ($subscription, $transaction, $reversedAt): bool {
            $this->recordedCharge($transaction, lock: true);

            $outstanding = SubscriptionTransaction::query()
                ->where('provider', $transaction->provider)
                ->where('provider_transaction_id', $transaction->transactionId)
                ->where('type', TransactionType::Refund)
                ->when($reversedAt, fn ($query) => $query->where('purchased_at', '<=', $reversedAt))
                ->whereDoesntHave('reversals')
                ->with('subscription')
                ->orderBy('id')
                ->get();

            if ($outstanding->isEmpty()) {
                return $this->hasLedgerEntry($transaction, TransactionType::RefundReversed);
            }

            foreach ($outstanding as $refund) {
                $this->recordTransaction(
                    $refund->subscription,
                    TransactionType::RefundReversed,
                    $transaction->withAmount($refund->money()),
                    'reversal:'.$refund->idempotency_key,
                    at: $reversedAt ?? now(),
                    related: $refund,
                );
            }

            $restores = $subscription->status === SubscriptionStatus::Cancelled
                && $subscription->cancelled_by === CancelledBy::System
                && $transaction->expiresAt?->isFuture();

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

            return true;
        }, fn (): bool => true);
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

        return DB::transaction(function () use ($subscription, $reason): Subscription {
            $subscription->update([
                'status' => SubscriptionStatus::Cancelled,
                'ends_at' => now(),
                'grace_ends_at' => null,
                'is_recurring' => false,
                'cancelled_by' => CancelledBy::System,
                'cancelled_reason' => $reason,
            ]);

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
     * Period columns for a new contract row, taken from the provider's own
     * dates rather than computed from the plan price — the store owns the
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
            'status' => $status,
            'is_recurring' => $transaction->autoRenews ?? true,
            'source' => SubscriptionSource::Purchase,
        ];
    }

    private function isUserCancelled(Subscription $subscription): bool
    {
        return $subscription->status === SubscriptionStatus::Cancelled
            && $subscription->cancelled_by === CancelledBy::User;
    }

    /**
     * Runs a ledger-writing change in one database transaction. If another
     * worker recorded the same money movement first, the unique index on
     * `idempotency_key` rejects this insert, everything this attempt changed
     * rolls back, and `$onDuplicate` resolves the result from what was
     * recorded instead.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @param  Closure(): T  $onDuplicate
     * @return T
     */
    private function idempotently(Closure $work, Closure $onDuplicate): mixed
    {
        try {
            return DB::transaction($work);
        } catch (UniqueConstraintViolationException) {
            return $onDuplicate();
        }
    }

    /** The ledger row already recorded under this key, if any. */
    protected function findRecorded(PaymentProvider $provider, string $idempotencyKey, bool $lock = false): ?SubscriptionTransaction
    {
        return SubscriptionTransaction::query()
            ->where('provider', $provider)
            ->where('idempotency_key', $idempotencyKey)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->with('subscription')
            ->first();
    }

    /** The charge already recorded for this provider transaction id — at most one can exist. */
    private function recordedCharge(ProviderTransaction $transaction, bool $lock = false): ?SubscriptionTransaction
    {
        $key = $this->chargeKey($transaction);

        return $key ? $this->findRecorded($transaction->provider, $key, $lock) : null;
    }

    /** The charge another worker recorded first, after this attempt lost the race on its key. */
    private function winningCharge(ProviderTransaction $transaction): SubscriptionTransaction
    {
        return $this->recordedCharge($transaction)
            ?? throw new LogicException("Ledger key collision with no recorded charge for transaction [{$transaction->transactionId}].");
    }

    private function hasLedgerEntry(ProviderTransaction $transaction, TransactionType $type): bool
    {
        return SubscriptionTransaction::query()
            ->where('provider', $transaction->provider)
            ->where('provider_transaction_id', $transaction->transactionId)
            ->where('type', $type)
            ->exists();
    }

    /**
     * One charge per provider transaction id, whatever its type — the same
     * transaction can't be both an initial purchase and a renewal. Null (a
     * unique `manual:` key is generated) when the provider gave no id.
     */
    private function chargeKey(ProviderTransaction $transaction): ?string
    {
        return $transaction->transactionId ? 'charge:'.$transaction->transactionId : null;
    }

    /** One key per refund event; a provider that refunds a transaction only once sends no event key. */
    private function refundKey(ProviderTransaction $transaction): ?string
    {
        return $transaction->transactionId
            ? 'refund:'.$transaction->transactionId.':'.($transaction->eventKey ?? 'full')
            : null;
    }

    /**
     * The part of the provider's cumulative refunded total not yet on the
     * ledger: total (capped at the recorded charge) minus refunds already
     * recorded and not reversed. Null when the provider sent no amount.
     */
    private function unrecordedRefund(ProviderTransaction $transaction, ?SubscriptionTransaction $charge): ?Money
    {
        $total = $transaction->amount;

        if ($total === null) {
            return null;
        }

        $charged = $charge?->money();

        if ($charged && $charged->currency === $total->currency && $total->minor > $charged->minor) {
            $total = $charged;
        }

        if (! $transaction->transactionId) {
            return $total;
        }

        // Refunds count positive and reversals negative here — the opposite of signedAmountSql().
        $alreadyRefunded = (int) SubscriptionTransaction::query()
            ->where('provider', $transaction->provider)
            ->where('provider_transaction_id', $transaction->transactionId)
            ->where('currency', $total->currency)
            ->whereIn('type', [TransactionType::Refund, TransactionType::RefundReversed])
            ->sum(DB::raw('-('.SubscriptionTransaction::signedAmountSql().')'));

        return $total->minus(Money::ofMinor($alreadyRefunded, $total->currency));
    }

    /**
     * Writes one money movement to the ledger under `$key` (null = a generated
     * `manual:` key). `$at` overrides when the money moved (a refund's
     * revocation time); a charge defaults to the provider's purchase time.
     * `$related` is the charge a refund refunds / the refund a reversal reverses.
     */
    private function recordTransaction(
        Subscription $subscription,
        TransactionType $type,
        ProviderTransaction $transaction,
        ?string $key,
        ?CarbonInterface $at = null,
        ?SubscriptionTransaction $related = null,
    ): SubscriptionTransaction {
        return $subscription->transactions()->create([
            'periods_covered' => $type->isCharge() ? $this->periodsCovered($subscription, $transaction) : 1,
            'idempotency_key' => $key,
            'related_transaction_id' => $related?->id,
            'provider' => $transaction->provider,
            'type' => $type,
            'amount_minor' => $transaction->amount?->minor,
            'currency' => $transaction->amount?->currency,
            'purchased_at' => $at ?? $transaction->purchasedAt ?? now(),
            'provider_transaction_id' => $transaction->transactionId,
            'provider_original_id' => $transaction->originalTransactionId,
            'payload' => [
                'expires_at' => $transaction->expiresAt?->toIso8601String(),
                'transaction' => $transaction->payload,
            ],
            'notification_provider' => $transaction->notification ? $transaction->provider : null,
            'notification_id' => $transaction->notification?->getKey(),
        ]);
    }

    /**
     * Billing periods a charge pays for: the span the provider granted
     * (purchase → expiry) in whole billing periods of the row's price, at
     * least one. A normal charge covers one; a three-month pay-up-front offer
     * on a monthly product covers three.
     */
    private function periodsCovered(Subscription $subscription, ProviderTransaction $transaction): int
    {
        $periodDays = $subscription->planPrice?->billingDurationInDays() ?? 0;

        if (! $transaction->purchasedAt || ! $transaction->expiresAt || $periodDays <= 0) {
            return 1;
        }

        $days = abs($transaction->purchasedAt->diffInSeconds($transaction->expiresAt)) / 86400;

        return max(1, (int) round($days / $periodDays));
    }

    /** @return array{amount: string|null, currency: string|null} */
    private function moneyProperties(?Money $money): array
    {
        return ['amount' => $money?->toDecimal(), 'currency' => $money?->currency];
    }

    /**
     * A contract kept charging after its account was purged: the money is
     * real and recorded (revenue), but no customer owns it any more — flag it
     * so someone can follow up (the store still bills that customer).
     */
    private function flagOwnerlessCharge(Subscription $subscription, ProviderTransaction $transaction): void
    {
        if ($subscription->user_id !== null) {
            return;
        }

        Log::channel('webhooks')->warning('Contract charged after its owner was purged — recorded without an owner, follow up with the provider', [
            'provider' => $transaction->provider->value,
            'subscription_id' => $subscription->id,
            'transaction_id' => $transaction->transactionId,
            'original_transaction_id' => $transaction->originalTransactionId,
            'amount' => $transaction->amount?->toDecimal(),
            'currency' => $transaction->amount?->currency,
            'notification_id' => $transaction->notification?->getKey(),
        ]);
    }

    /** @param  array<string, mixed>  $properties */
    private function log(Subscription $subscription, ActivityAction $action, string $type, array $properties = []): void
    {
        $owner = $subscription->user()->withTrashed()->first();

        ActivityLogger::log(
            ActivityModule::User,
            $action,
            $owner,
            [
                'type' => $type,
                'plan' => $subscription->plan->name,
                'provider' => $subscription->provider->value,
                ...($owner ? [] : ['subscription_id' => $subscription->id, 'ownerless' => true]),
                ...$properties,
            ],
            causer: null,
            context: ActivityContext::Webhook,
        );
    }
}
