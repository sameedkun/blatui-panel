<?php

namespace App\Models;

use App\Enum\CancelledBy;
use App\Enum\PaymentProvider;
use App\Enum\SubscriptionSource;
use App\Enum\SubscriptionStatus;
use App\Support\Money\Money;
use Carbon\CarbonInterface;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user's entitlement to a plan for a period — the access/lifecycle record.
 * It holds no money: what the customer actually paid (any number of charges,
 * renewals and refunds, each in its own currency) lives in
 * {@see SubscriptionTransaction}. `plan_price_id` is the configured price
 * selected when the subscription was created, never a record of what was paid.
 *
 * `provider` is the billing integration (local, appstore, …); `source` is why
 * the subscription exists (bought, granted by an admin, promotional, …).
 */
#[Fillable([
    'user_id',
    'plan_id',
    'plan_price_id',
    'starts_at',
    'ends_at',
    'trial_ends_at',
    'grace_ends_at',
    'status',
    'cancelled_by',
    'cancelled_reason',
    'is_recurring',
    'provider',
    'source',
    'granted_by',
    'grant_reason',
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
            'status' => SubscriptionStatus::class,
            'cancelled_by' => CancelledBy::class,
            'is_recurring' => 'boolean',
            'provider' => PaymentProvider::class,
            'source' => SubscriptionSource::class,
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
     * Get the staff member who granted this subscription, when it was granted.
     *
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Get the financial transactions (charges, renewals, refunds) recorded
     * against this subscription.
     *
     * @return HasMany<SubscriptionTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(SubscriptionTransaction::class);
    }

    /** Free access given by someone rather than bought. */
    public function isGrant(): bool
    {
        return $this->source->isGrant();
    }

    /**
     * Net amount collected on this row (charges − refunds + reversals), one
     * entry per currency — currencies are never added together. Uses the
     * loaded `transactions` relation when present.
     *
     * @return array<string, Money> currency => net amount
     */
    public function netPaid(): array
    {
        $totals = [];

        foreach ($this->transactions as $transaction) {
            if ($money = $transaction->signedMoney()) {
                $totals[$money->currency] = isset($totals[$money->currency]) ? $totals[$money->currency]->plus($money) : $money;
            }
        }

        return $totals;
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
    public function accessEndsAt(): ?CarbonInterface
    {
        return $this->grace_ends_at ?? $this->ends_at;
    }
}
