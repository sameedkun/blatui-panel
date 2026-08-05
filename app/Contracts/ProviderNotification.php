<?php

namespace App\Contracts;

use App\Support\WebhookNotificationRegistry;
use Carbon\CarbonInterface;

/**
 * A single raw inbound webhook notification from a payment/subscription
 * provider (Apple, RevenueCat, Google, Stripe, ...). Each provider has its
 * own table and its own raw payload shape — this contract is the one thing
 * every provider's model agrees to expose, so the admin panel (and
 * {@see WebhookNotificationRegistry}) never needs to know which
 * provider it's rendering. `notificationType()`/`environment()` stay plain
 * strings rather than a shared enum, since providers don't share a vocabulary
 * — implementations resolve their own provider-specific enum for a label.
 */
interface ProviderNotification
{
    public function notificationType(): string;

    public function transactionId(): ?string;

    public function originalTransactionId(): ?string;

    public function productId(): ?string;

    public function environment(): ?string;

    public function occurredAt(): ?CarbonInterface;

    public function isProcessed(): bool;

    public function processedAt(): ?CarbonInterface;

    /** @return array<string, mixed> */
    public function rawPayload(): array;
}
