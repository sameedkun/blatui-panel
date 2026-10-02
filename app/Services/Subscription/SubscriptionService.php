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
use App\Exceptions\StoreManagedSubscriptionException;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Money\Currency;
use App\Support\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubscriptionService
{
    /**
     * Start a brand-new subscription, replacing any existing active one.
     *
     * `$isRecurring` defaults to false: admin-assigned (`local`) and one-time
     * payment (e.g. Oxapay) subscriptions don't renew, and they're the majority
     * case here. Recurring gateway integrations pass `true` explicitly.
     *
     * This only creates the entitlement — it never records money. An admin
     * assignment is a grant (`$source`, `$grantedBy`, `$grantReason`) with no
     * transaction; a real payment must be written to the transaction ledger
     * by whatever took it.
     */
    public function subscribe(
        User $user,
        PlanPrice $price,
        PaymentProvider $provider = PaymentProvider::Local,
        bool $isRecurring = false,
        SubscriptionSource $source = SubscriptionSource::Admin,
        ?User $grantedBy = null,
        ?string $grantReason = null,
    ): Subscription {
        $this->assertNotStoreManaged($user);

        return DB::transaction(function () use ($user, $price, $provider, $isRecurring, $source, $grantedBy, $grantReason) {
            // close the previous active subscription
            $this->cancelActive($user, CancelledBy::System, 'Replaced by new subscription', true);

            [$startsAt, $trialEndsAt, $endsAt, $graceEndsAt] = $this->computeDates($price, $isRecurring);

            $subscription = $user->subscriptions()->create([
                'plan_id' => $price->plan_id,
                'plan_price_id' => $price->id,
                'starts_at' => $startsAt,
                'trial_ends_at' => $trialEndsAt,
                'ends_at' => $endsAt,
                'grace_ends_at' => $graceEndsAt,
                'status' => $trialEndsAt ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'is_recurring' => $isRecurring,
                'provider' => $provider,
                ...$this->grantAttributes($source, $grantedBy, $grantReason),
            ]);

            ActivityLogger::log(ActivityModule::User, ActivityAction::Assigned, $user, [
                'type' => 'subscription_assigned',
                'plan' => $price->plan->name,
                'provider' => $provider->value,
                'source' => $source->value,
                'reason' => $subscription->grant_reason,
            ]);

            return $subscription;
        });
    }

    /**
     * Refuse to replace a live subscription a store bills: the store would keep
     * charging the customer and its next notification would revive the row.
     * Plan changes for those happen in the store; cancelling one stays allowed.
     *
     * @throws StoreManagedSubscriptionException
     */
    public function assertNotStoreManaged(User $user): void
    {
        $this->assertReplaceable($user->activeSubscription()->first());
    }

    /**
     * {@see assertNotStoreManaged()} for an already-loaded active subscription.
     *
     * @throws StoreManagedSubscriptionException
     */
    public function assertReplaceable(?Subscription $active): void
    {
        if ($active && $active->provider !== PaymentProvider::Local) {
            throw new StoreManagedSubscriptionException($active->provider);
        }
    }

    /**
     * Replace the current subscription with a new plan/price, prorating the
     * remaining balance and linking the previous subscription in the chain.
     *
     * `$isRecurring` defaults to false for the same reason as {@see subscribe()}:
     * admin-assigned and one-time-payment subscriptions don't renew. Recurring
     * gateway integrations pass `true` explicitly. Grant metadata and the
     * no-money rule are the same as {@see subscribe()}.
     */
    public function upgrade(
        User $user,
        PlanPrice $newPrice,
        PaymentProvider $provider = PaymentProvider::Local,
        bool $isRecurring = false,
        SubscriptionSource $source = SubscriptionSource::Admin,
        ?User $grantedBy = null,
        ?string $grantReason = null,
    ): Subscription {
        $this->assertNotStoreManaged($user);

        return DB::transaction(function () use ($user, $newPrice, $provider, $isRecurring, $source, $grantedBy, $grantReason) {
            $current = $user->activeSubscription;
            $proration = $current ? $this->prorationCredit($current, $newPrice->currency) : ['credit' => null, 'skipped' => null];
            $credit = $proration['credit']?->toFloat() ?? 0;
            $skipped = $proration['skipped'];
            $paidCurrency = $current ? $this->currentCharge($current)?->currency : null;

            [$startsAt, $trialEndsAt, $endsAt, $graceEndsAt] = $this->computeDates($newPrice, $isRecurring);
            $startsAt = $current?->starts_at ?? $startsAt;

            $newSub = $user->subscriptions()->create([
                'plan_id' => $newPrice->plan_id,
                'plan_price_id' => $newPrice->id,
                'starts_at' => $startsAt,
                'trial_ends_at' => $trialEndsAt,
                'ends_at' => $endsAt,
                'grace_ends_at' => $graceEndsAt,
                'status' => $trialEndsAt ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'is_recurring' => $isRecurring,
                'provider' => $provider,
                ...$this->grantAttributes($source, $grantedBy, $grantReason),
                'previous_subscription_id' => $current?->id,
                'proration_meta' => array_filter([
                    'credit' => $credit,
                    'credit_skipped' => $skipped === 'currency_mismatch' ? $skipped : null,
                    'paid_currency' => $skipped === 'currency_mismatch' ? $paidCurrency : null,
                    'from_plan' => $current?->plan->slug,
                    'new_amount' => $newPrice->amount,
                ], fn ($value): bool => $value !== null),
            ]);

            // Capture before the update below mutates $current away.
            $fromPlanName = $current?->plan->name;

            $current?->update([
                'status' => SubscriptionStatus::Cancelled,
                'ends_at' => now(),
                'is_recurring' => false,
                'cancelled_by' => CancelledBy::System,
                'cancelled_reason' => 'Upgraded to: '.$newPrice->plan->slug,
            ]);

            ActivityLogger::log(ActivityModule::User, ActivityAction::Assigned, $user, [
                'type' => 'subscription_upgraded',
                'from_plan' => $fromPlanName,
                'to_plan' => $newPrice->plan->name,
                'credit_applied' => $credit,
                'credit_skipped' => $skipped,
                'currency' => $newPrice->currency,
                'source' => $source->value,
                'reason' => $newSub->grant_reason,
            ]);

            return $newSub;
        });
    }

    public function cancelActive(
        User $user,
        CancelledBy $cancelledBy = CancelledBy::User,
        ?string $reason = null,
        bool $immediately = false
    ): bool {
        $sub = $user->activeSubscription;
        if (! $sub) {
            return true;
        }

        $this->cancelSubscription($sub, $cancelledBy, $reason, $immediately);

        return true;
    }

    /**
     * Cancel a specific subscription row directly, rather than looking one up
     * off a user's current {@see Subscription}. Used by
     * {@see cancelActive()} and by callers (e.g. account merge) that already
     * hold the exact row to cancel and can't rely on `$user->activeSubscription`
     * resolving to it (a user may end up owning more than one active-looking
     * row mid-merge).
     *
     * `$causer`/`$context` override the audit entry's auto-detected values —
     * needed by provider webhook processing ({@see ProviderSubscriptionService}),
     * which runs on a queue with no `auth()` session.
     */
    public function cancelSubscription(
        Subscription $sub,
        CancelledBy $cancelledBy = CancelledBy::User,
        ?string $reason = null,
        bool $immediately = false,
        Model|null|false $causer = false,
        ?ActivityContext $context = null,
    ): void {
        $reasonText = $reason ?: 'Cancelled';

        $data = [
            'cancelled_by' => $cancelledBy,
            'cancelled_reason' => $reasonText,
            'is_recurring' => false,
            'status' => SubscriptionStatus::Cancelled,
        ];

        // if immediate or already expired then end now, otherwise keep access until period end
        if ($immediately || ($sub->ends_at !== null && now()->gte($sub->ends_at))) {
            $data['ends_at'] = now();
        }

        $sub->update($data);

        ActivityLogger::log(ActivityModule::User, ActivityAction::Cancelled, $sub->user, [
            'type' => 'subscription_cancelled',
            'plan' => $sub->plan->name,
            'cancelled_by' => $cancelledBy->value,
            'reason' => $reasonText,
            'immediately' => $immediately,
            'access_until' => $sub->ends_at?->toIso8601String(),
        ], $causer, $context);
    }

    /**
     * Undo a cancellation while the subscription is still cancelled-but-live
     * (status `cancelled`, `ends_at` still in the future) — restores the previous
     * status and clears the cancellation reason. Not available once access has
     * actually lapsed; assign a plan instead at that point.
     *
     * `$isRecurring` defaults to false for the same reason as {@see subscribe()}:
     * reactivation shouldn't silently switch an admin-assigned or one-time
     * subscription into a renewing one. Recurring gateway integrations pass `true`.
     */
    public function reactivate(User $user, bool $isRecurring = false): Subscription
    {
        $sub = $user->subscriptions()
            ->where('status', SubscriptionStatus::Cancelled)
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();

        if (! $sub) {
            throw new InvalidArgumentException('This user has no cancelled subscription that can be reactivated.');
        }

        $sub->update([
            'status' => $sub->trial_ends_at && now()->lt($sub->trial_ends_at)
                ? SubscriptionStatus::Trialing
                : SubscriptionStatus::Active,
            'is_recurring' => $isRecurring,
            'cancelled_by' => null,
            'cancelled_reason' => null,
        ]);

        ActivityLogger::log(ActivityModule::User, ActivityAction::Updated, $user, [
            'type' => 'subscription_reactivated',
            'plan' => $sub->plan->name,
        ]);

        return $sub;
    }

    /**
     * The unused share of the current billing period's payment on `$sub`, as
     * credit towards a new price in `$currency`.
     *
     * Only the latest charge counts (earlier renewals paid for periods already
     * used), net of any refunds/reversals recorded against that charge, scaled
     * by the time left in the period and never more than was paid.
     *
     * No credit — with the reason — when nothing was paid (`no_payment`: a
     * grant, or fully refunded), the period is over (`period_ended`), or the
     * payment was in another currency (`currency_mismatch`): amounts in
     * different currencies are never compared, and there is no FX conversion.
     *
     * @return array{credit: Money|null, skipped: 'no_payment'|'period_ended'|'currency_mismatch'|null}
     */
    public function prorationCredit(Subscription $sub, string $currency): array
    {
        $charge = $this->currentCharge($sub);
        $paid = $charge?->money();

        if ($paid === null) {
            return ['credit' => null, 'skipped' => 'no_payment'];
        }

        if ($paid->currency !== Currency::normalize($currency)) {
            return ['credit' => null, 'skipped' => 'currency_mismatch'];
        }

        $paid = $paid->minus($this->refundedAgainst($charge));
        $periodSeconds = $sub->planPrice->billingDurationInDays() * 86400;
        $remainingSeconds = $sub->ends_at ? (int) max(0, now()->diffInSeconds($sub->ends_at, false)) : 0;

        if ($remainingSeconds === 0 || $periodSeconds <= 0) {
            return ['credit' => null, 'skipped' => 'period_ended'];
        }

        if ($paid->minor <= 0) {
            return ['credit' => null, 'skipped' => 'no_payment'];
        }

        return ['credit' => $paid->multipliedBy(min($remainingSeconds, $periodSeconds), $periodSeconds), 'skipped' => null];
    }

    /** The most recent priced charge on the row — what paid for the current period. */
    private function currentCharge(Subscription $sub): ?SubscriptionTransaction
    {
        return $sub->transactions()
            ->charges()
            ->priced()
            ->latest('purchased_at')
            ->latest('id')
            ->first();
    }

    /** Net refunded against one charge: its refunds minus the reversals of those refunds. */
    private function refundedAgainst(SubscriptionTransaction $charge): Money
    {
        $refunds = SubscriptionTransaction::query()
            ->where('related_transaction_id', $charge->id)
            ->where('type', TransactionType::Refund)
            ->get(['id', 'amount_minor']);

        $reversed = (int) SubscriptionTransaction::query()
            ->whereIn('related_transaction_id', $refunds->pluck('id'))
            ->where('type', TransactionType::RefundReversed)
            ->sum('amount_minor');

        return Money::ofMinor((int) $refunds->sum('amount_minor') - $reversed, (string) $charge->currency);
    }

    /**
     * Who/why columns for a new row. Only a grant carries grant metadata,
     * even if a caller passes it for a purchase.
     *
     * @return array{source: SubscriptionSource, granted_by: int|null, grant_reason: string|null}
     */
    private function grantAttributes(SubscriptionSource $source, ?User $grantedBy, ?string $grantReason): array
    {
        $reason = trim((string) $grantReason);

        return [
            'source' => $source,
            'granted_by' => $source->isGrant() ? $grantedBy?->getKey() : null,
            'grant_reason' => $source->isGrant() && $reason !== '' ? $reason : null,
        ];
    }

    /**
     * @return array{0: CarbonInterface, 1: ?CarbonInterface, 2: CarbonInterface, 3: ?CarbonInterface}
     */
    protected function computeDates(PlanPrice $price, bool $isRecurring): array
    {
        $startsAt = now();
        $trialEndsAt = $price->trialEndsAt();
        $billingDays = $price->billingDurationInDays();

        // Non-recurring with a trial: there's no paid period to run into, so access
        // ends where the trial does. Setting this correctly up front means nothing
        // downstream has to rewrite ends_at later.
        $endsAt = ($trialEndsAt && ! $isRecurring)
            ? $trialEndsAt->copy()
            : ($trialEndsAt ?? $startsAt)->copy()->addDays($billingDays);

        // Grace only means anything when there's a renewal charge that might fail.
        $graceEndsAt = $isRecurring ? $price->graceEndsAt($endsAt) : null;

        return [$startsAt, $trialEndsAt, $endsAt, $graceEndsAt];
    }
}
