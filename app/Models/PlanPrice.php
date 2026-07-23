<?php

namespace App\Models;

use App\Enum\BillingInterval;
use Carbon\CarbonInterface;
use Database\Factories\PlanPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'plan_id',
    'amount',
    'compare_at_amount',
    'currency',
    'billing_period',
    'billing_interval',
    'trial_period',
    'trial_interval',
    'grace_period',
    'grace_interval',
    'is_active',
])]
class PlanPrice extends Model
{
    /** @use HasFactory<PlanPriceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'compare_at_amount' => 'decimal:2',
            'billing_interval' => BillingInterval::class,
            'trial_interval' => BillingInterval::class,
            'grace_interval' => BillingInterval::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the plan this price belongs to.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the payment-provider mappings for this price.
     *
     * @return HasMany<PlanPriceProvider, $this>
     */
    public function providers(): HasMany
    {
        return $this->hasMany(PlanPriceProvider::class);
    }

    /**
     * Get all subscriptions purchased at this price.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function billingDurationInDays(): int
    {
        return match ($this->billing_interval) {
            BillingInterval::Day => $this->billing_period,
            BillingInterval::Week => $this->billing_period * 7,
            BillingInterval::Month => $this->billing_period * 30,
            BillingInterval::Year => $this->billing_period * 365,
        };
    }

    public function trialEndsAt(): ?CarbonInterface
    {
        if ($this->trial_period <= 0) {
            return null;
        }

        return now()->add($this->trial_interval->value, $this->trial_period);
    }

    public function graceEndsAt(CarbonInterface $endsAt): ?CarbonInterface
    {
        if ($this->grace_period <= 0) {
            return null;
        }

        return $endsAt->copy()->add($this->grace_interval->value, $this->grace_period);
    }
}
