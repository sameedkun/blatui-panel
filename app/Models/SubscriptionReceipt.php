<?php

namespace App\Models;

use App\Contracts\ProviderNotification;
use App\Enum\PaymentProvider;
use App\Enum\ReceiptType;
use App\Support\WebhookNotificationRegistry;
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
    'notification_provider',
    'notification_id',
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
            'notification_provider' => PaymentProvider::class,
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

    /**
     * The raw provider webhook notification this receipt was recorded from,
     * if linked — resolved via {@see WebhookNotificationRegistry} rather than
     * a native polymorphic relation, since `notification_provider` names a
     * provider, not a model class.
     */
    public function notification(): ?ProviderNotification
    {
        return WebhookNotificationRegistry::resolve($this->notification_provider, $this->notification_id);
    }
}
