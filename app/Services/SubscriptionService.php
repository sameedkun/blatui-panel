<?php

namespace App\Services;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ActivityLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubscriptionService
{
    public function subscribe(User $user, PlanPrice $price, string $provider = 'local'): Subscription
    {
        return DB::transaction(function () use ($user, $price, $provider) {
            // close the previous active subscription
            $this->cancelActive($user, 'system', 'Replaced by new subscription', true);

            [$startsAt, $trialEndsAt, $endsAt, $graceEndsAt] = $this->computeDates($price);

            $subscription = $user->subscriptions()->create([
                'plan_id' => $price->plan_id,
                'plan_price_id' => $price->id,
                'starts_at' => $startsAt,
                'trial_ends_at' => $trialEndsAt,
                'ends_at' => $endsAt,
                'grace_ends_at' => $graceEndsAt,
                'amount_paid' => $price->amount,
                'currency' => $price->currency,
                'status' => $trialEndsAt ? 'trialing' : 'active',
                'is_recurring' => true,
                'provider' => $provider,
            ]);

            ActivityLogger::log(ActivityModule::User, ActivityAction::Assigned, $user, [
                'type' => 'subscription_assigned',
                'plan' => $price->plan->name,
                'amount' => (string) $price->amount,
                'currency' => $price->currency,
                'provider' => $provider,
            ]);

            return $subscription;
        });
    }

    public function upgrade(User $user, PlanPrice $newPrice, string $provider = 'local'): Subscription
    {
        return DB::transaction(function () use ($user, $newPrice, $provider) {
            $current = $user->activeSubscription;
            $credit = $current ? $this->prorationCredit($current) : 0;
            $amountDue = max(0, $newPrice->amount - $credit);

            [$startsAt, $trialEndsAt, $endsAt, $graceEndsAt] = $this->computeDates($newPrice);
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
                'status' => $trialEndsAt ? 'trialing' : 'active',
                'is_recurring' => true,
                'provider' => $provider,
                'previous_subscription_id' => $current?->id,
                'proration_meta' => [
                    'credit' => $credit,
                    'from_plan' => $current?->plan->slug,
                    'new_amount' => $newPrice->amount,
                ],
            ]);

            $current?->update([
                'status' => 'cancelled',
                'ends_at' => now(),
                'is_recurring' => false,
                'cancelled_by' => 'system',
                'cancelled_reason' => 'Upgraded to: '.$newPrice->plan->slug,
            ]);

            ActivityLogger::log(ActivityModule::User, ActivityAction::Assigned, $user, [
                'type' => 'subscription_upgraded',
                'from_plan' => $current?->plan->name,
                'to_plan' => $newPrice->plan->name,
                'credit_applied' => $credit,
                'amount_charged' => $amountDue,
                'currency' => $newPrice->currency,
            ]);

            return $newSub;
        });
    }

    /**
     * @param  'user'|'admin'|'system'  $cancelledBy
     */
    public function cancelActive(
        User $user,
        string $cancelledBy = 'user',
        ?string $reason = null,
        bool $immediately = false
    ): bool {
        $sub = $user->activeSubscription;
        if (! $sub) {
            return true;
        }

        $reasonText = $reason ?: 'Cancelled';

        $data = [
            'cancelled_by' => $cancelledBy,
            'cancelled_reason' => $reasonText,
            'is_recurring' => false,
            'status' => 'cancelled',
        ];

        // if immediate or already expired then end now, otherwise keep access until period end
        if ($immediately || now()->gte($sub->ends_at)) {
            $data['ends_at'] = now();
        }

        $sub->update($data);

        ActivityLogger::log(ActivityModule::User, ActivityAction::Cancelled, $user, [
            'type' => 'subscription_cancelled',
            'plan' => $sub->plan->name,
            'cancelled_by' => $cancelledBy,
            'reason' => $reasonText,
            'immediately' => $immediately,
            'access_until' => $sub->ends_at?->toIso8601String(),
        ]);

        return true;
    }

    /**
     * Undo a cancellation while the subscription is still cancelled-but-live
     * (status `cancelled`, `ends_at` still in the future) — restores
     * auto-renewal and clears the cancellation reason. Not available once
     * access has actually lapsed; assign a plan instead at that point.
     */
    public function reactivate(User $user): Subscription
    {
        $sub = $user->subscriptions()
            ->where('status', 'cancelled')
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();

        if (! $sub) {
            throw new InvalidArgumentException('This user has no cancelled subscription that can be reactivated.');
        }

        $sub->update([
            'status' => $sub->trial_ends_at && now()->lt($sub->trial_ends_at) ? 'trialing' : 'active',
            'is_recurring' => true,
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
    protected function computeDates(PlanPrice $price): array
    {
        $startsAt = now();
        $trialEndsAt = $price->trialEndsAt();      // null if trial 0
        $billingDays = $price->billingDurationInDays();

        $endsAt = ($trialEndsAt ?? $startsAt)->copy()->addDays($billingDays);
        $graceEndsAt = $price->graceEndsAt($endsAt); // null if grace 0

        return [$startsAt, $trialEndsAt, $endsAt, $graceEndsAt];
    }
}
