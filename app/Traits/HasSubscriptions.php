<?php

namespace App\Traits;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasSubscriptions
{
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where(function ($q) {
                $q->whereIn('status', ['trialing', 'active'])
                    ->where('ends_at', '>', now())
                    ->orWhere(fn ($q2) => $q2
                        ->where('status', 'grace')
                        ->where('grace_ends_at', '>', now()))
                    ->orWhere(fn ($q3) => $q3
                        ->where('status', 'cancelled')
                        ->where('ends_at', '>', now())); // cancelled but access remaining
            })
            ->latest('ends_at');
    }

    public function isSubscribed(): bool
    {
        return $this->activeSubscription()->exists();
    }

    public function isSubscribedTo(Plan $plan): bool
    {
        $sub = $this->activeSubscription;

        return $sub && $sub->plan_id === $plan->id;
    }

    public function isOnTrial(): bool
    {
        $sub = $this->activeSubscription;

        return $sub && $sub->status === 'trialing' && now()->lt($sub->trial_ends_at);
    }

    public function isInGrace(): bool
    {
        $sub = $this->activeSubscription;

        return $sub && $sub->status === 'grace' && now()->lt($sub->grace_ends_at);
    }

    public function currentPlan(): ?Plan
    {
        return $this->activeSubscription?->plan;
    }

    // features tak seedha shortcut — controller me kaam aayega
    public function planFeature(string $key, mixed $fallback = null): mixed
    {
        return $this->currentPlan()?->feature($key, $fallback) ?? $fallback;
    }
}