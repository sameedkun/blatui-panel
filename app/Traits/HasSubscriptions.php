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
            ->where('status', '!=', 'failed')
            ->where(function ($q) {
                $q->where('ends_at', '>', now())
                    ->orWhere('grace_ends_at', '>', now());
            })
            ->latest('ends_at');
    }

    public function isSubscribed(): bool
    {
        return $this->activeSubscription !== null;
    }

    public function isSubscribedTo(Plan $plan): bool
    {
        return $this->activeSubscription?->plan_id === $plan->id;
    }

    public function isOnTrial(): bool
    {
        return (bool) $this->activeSubscription?->isOnTrial();
    }

    public function isInGrace(): bool
    {
        return (bool) $this->activeSubscription?->isInGrace();
    }

    public function currentPlan(): ?Plan
    {
        return $this->activeSubscription?->plan;
    }

    public function planFeature(string $key, mixed $fallback = null): mixed
    {
        return $this->currentPlan()?->feature($key, $fallback) ?? $fallback;
    }
}
