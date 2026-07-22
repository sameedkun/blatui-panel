<?php

namespace App\Models;

use App\Enum\PaymentProvider;
use App\Enum\ReceiptType;
use Database\Factories\SubscriptionReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'subscription_id',
    'provider',
    'type',
    'provider_transaction_id',
    'provider_original_id',
    'payload',
])]
class SubscriptionReceipt extends Model
{
    /** @use HasFactory<SubscriptionReceiptFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'type' => ReceiptType::class,
            'payload' => 'array',
        ];
    }

    /**
     * Get the subscription this receipt belongs to.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
