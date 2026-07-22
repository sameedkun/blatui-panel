<?php

namespace App\Services;

use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function subscribe(User $user, PlanPrice $price, string $provider = 'local'): Subscription
    {
        return DB::transaction(function () use ($user, $price, $provider) {
            // close the previous active subscription
            $this->cancelActive($user, 'system', 'Replaced by new subscription', true);

            [$startsAt, $trialEndsAt, $endsAt, $graceEndsAt] = $this->computeDates($price);

            return $user->subscriptions()->create([
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

            return $newSub;
        });
    }

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

        $data = [
            'cancelled_by' => $cancelledBy,
            'cancelled_reason' => $reason ?: 'Cancelled',
            'is_recurring' => false,
            'status' => 'cancelled',
        ];

        // if immediate or already expired then end now, otherwise keep access until period end
        if ($immediately || now()->gte($sub->ends_at)) {
            $data['ends_at'] = now();
        }

        $sub->update($data);

        return true;
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
     * @return array{0: \Illuminate\Support\Carbon, 1: ?\Illuminate\Support\Carbon, 2: \Illuminate\Support\Carbon, 3: ?\Illuminate\Support\Carbon}
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