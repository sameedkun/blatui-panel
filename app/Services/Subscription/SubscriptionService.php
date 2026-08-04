<?php

namespace App\Services\Subscription;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\CancelledBy;
use App\Enum\PaymentProvider;
use App\Enum\SubscriptionStatus;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ActivityLogger;
use Carbon\CarbonInterface;
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
     */
    public function subscribe(User $user, PlanPrice $price, PaymentProvider $provider = PaymentProvider::Local, bool $isRecurring = false): Subscription
    {
        return DB::transaction(function () use ($user, $price, $provider, $isRecurring) {
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
                'amount_paid' => $price->amount,
                'currency' => $price->currency,
                'status' => $trialEndsAt ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'is_recurring' => $isRecurring,
                'provider' => $provider,
            ]);

            ActivityLogger::log(ActivityModule::User, ActivityAction::Assigned, $user, [
                'type' => 'subscription_assigned',
                'plan' => $price->plan->name,
                'amount' => (string) $price->amount,
                'currency' => $price->currency,
                'provider' => $provider->value,
            ]);

            return $subscription;
        });
    }

    /**
     * Replace the current subscription with a new plan/price, prorating the
     * remaining balance and linking the previous subscription in the chain.
     *
     * `$isRecurring` defaults to false for the same reason as {@see subscribe()}:
     * admin-assigned and one-time-payment subscriptions don't renew. Recurring
     * gateway integrations pass `true` explicitly.
     */
    public function upgrade(User $user, PlanPrice $newPrice, PaymentProvider $provider = PaymentProvider::Local, bool $isRecurring = false): Subscription
    {
        return DB::transaction(function () use ($user, $newPrice, $provider, $isRecurring) {
            $current = $user->activeSubscription;
            $credit = $current ? $this->prorationCredit($current) : 0;
            $amountDue = max(0, $newPrice->amount - $credit);

            [$startsAt, $trialEndsAt, $endsAt, $graceEndsAt] = $this->computeDates($newPrice, $isRecurring);
            $startsAt = $current?->starts_at ?? $startsAt;

            $newSub = $user->subscriptions()->create([
                'plan_id' => $newPrice->plan_id,
                'plan_price_id' => $newPrice->id,
                'starts_at' => $startsAt,
                'trial_ends_at' => $trialEndsAt,
                'ends_at' => $endsAt,
                'grace_ends_at' => $graceEndsAt,
                'amount_paid' => $amountDue,
                'currency' => $newPrice->currency,
                'status' => $trialEndsAt ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'is_recurring' => $isRecurring,
                'provider' => $provider,
                'previous_subscription_id' => $current?->id,
                'proration_meta' => [
                    'credit' => $credit,
                    'from_plan' => $current?->plan->slug,
                    'new_amount' => $newPrice->amount,
                ],
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
                'amount_charged' => $amountDue,
                'currency' => $newPrice->currency,
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
     */
    public function cancelSubscription(
        Subscription $sub,
        CancelledBy $cancelledBy = CancelledBy::User,
        ?string $reason = null,
        bool $immediately = false
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
        ]);
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

    public function prorationCredit(Subscription $sub): float
    {
        if (! $sub->ends_at || ! $sub->amount_paid) {
            return 0;
        }

        $remainingDays = now()->diffInDays($sub->ends_at, false);
        $totalDays = $sub->planPrice->billingDurationInDays();

        if ($remainingDays <= 0 || $totalDays <= 0) {
            return 0;
        }

        return round(($sub->amount_paid / $totalDays) * $remainingDays, 2);
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
