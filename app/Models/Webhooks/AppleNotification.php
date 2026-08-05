<?php

namespace App\Models\Webhooks;

use App\Contracts\ProviderNotification;
use App\Contracts\RedispatchableNotification;
use App\Enum\AppleNotificationSubtype;
use App\Enum\AppleNotificationType;
use App\Events\Webhooks\AppStoreWebhookReceived;
use Carbon\CarbonInterface;
use Database\Factories\Webhooks\AppleNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per Apple App Store Server Notification V2 received. Raw ingestion
 * log — the environment (`Production`/`Sandbox`) lives inside `payload`,
 * not as its own column, since it's only ever needed for display.
 */
#[Fillable([
    'notification_type',
    'subtype',
    'notification_uuid',
    'version',
    'signed_date',
    'payload',
    'transaction_info',
    'renewal_info',
    'app_account_token',
    'original_transaction_id',
    'transaction_id',
    'product_id',
    'processed',
    'processed_at',
])]
class AppleNotification extends Model implements ProviderNotification, RedispatchableNotification
{
    /** @use HasFactory<AppleNotificationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'notification_type' => AppleNotificationType::class,
            'subtype' => AppleNotificationSubtype::class,
            'signed_date' => 'datetime',
            'payload' => 'array',
            'transaction_info' => 'array',
            'renewal_info' => 'array',
            'processed' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public function notificationType(): string
    {
        return $this->notification_type->value;
    }

    /**
     * Not part of the {@see ProviderNotification} contract (providers don't
     * share a label vocabulary) — the generic admin partial calls this via
     * `method_exists()` when present and falls back to a headline of the raw
     * value otherwise, so a provider without one still renders sensibly.
     */
    public function notificationTypeLabel(): string
    {
        return $this->notification_type->label();
    }

    public function subtypeLabel(): ?string
    {
        return $this->subtype?->label();
    }

    public function transactionId(): ?string
    {
        return $this->transaction_id;
    }

    public function originalTransactionId(): ?string
    {
        return $this->original_transaction_id;
    }

    public function productId(): ?string
    {
        return $this->product_id;
    }

    /** Apple's `environment` (`Production`/`Sandbox`) lives inside `payload`, not a dedicated column. */
    public function environment(): ?string
    {
        return $this->payload['data']['environment'] ?? null;
    }

    public function occurredAt(): ?CarbonInterface
    {
        return $this->signed_date;
    }

    public function isProcessed(): bool
    {
        return $this->processed;
    }

    public function processedAt(): ?CarbonInterface
    {
        return $this->processed_at;
    }

    /** @return array<string, mixed> */
    public function rawPayload(): array
    {
        return $this->payload;
    }

    public function redispatch(): void
    {
        AppStoreWebhookReceived::dispatch(
            $this->notification_type,
            $this->subtype,
            $this->transaction_info,
            $this->renewal_info,
            $this->payload,
            $this,
        );
    }
}
