<?php

namespace App\Models;

use App\Enum\CancelledBy;
use App\Enum\PaymentProvider;
use App\Enum\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'user_id',
    'plan_id',
    'plan_price_id',
    'starts_at',
    'ends_at',
    'trial_ends_at',
    'grace_ends_at',
    'amount_paid',
    'currency',
    'status',
    'cancelled_by',
    'cancelled_reason',
    'is_recurring',
    'provider',
    'previous_subscription_id',
    'proration_meta',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'amount_paid' => 'decimal:2',
            'status' => SubscriptionStatus::class,
            'cancelled_by' => CancelledBy::class,
            'is_recurring' => 'boolean',
            'provider' => PaymentProvider::class,
            'proration_meta' => 'array',
        ];
    }

    /**
     * Get the user this subscription belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the plan this subscription is on.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the specific price this subscription was purchased at.
     *
     * @return BelongsTo<PlanPrice, $this>
     */
    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class);
    }

    /**
     * Get the subscription this one replaced (e.g. an upgrade/downgrade), if any.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function previousSubscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the provider receipts recorded against this subscription.
     *
     * @return HasMany<SubscriptionReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(SubscriptionReceipt::class);
    }

    /** Whether this subscription currently entitles the user to access. */
    public function isActive(): bool
    {
        if ($this->status === SubscriptionStatus::Failed) {
            return false;
        }

        $until = $this->accessEndsAt();

        return $until !== null && $until->isFuture();
    }

    /** Trial window abhi chal rahi hai. */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /** Billing period khatam, grace window me hai. */
    public function isInGrace(): bool
    {
        return $this->ends_at !== null
            && $this->grace_ends_at !== null
            && $this->ends_at->isPast()
            && $this->grace_ends_at->isFuture();
    }

    /** Access khatam hone ka actual waqt (grace samet). */
    public function accessEndsAt(): ?Carbon
    {
        return $this->grace_ends_at ?? $this->ends_at;
    }
}
