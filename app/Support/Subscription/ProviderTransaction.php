<?php

namespace App\Support\Subscription;

use App\Contracts\ProviderNotification;
use App\Enum\PaymentProvider;
use App\Services\Subscription\ProviderSubscriptionService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * One provider-side billing transaction, normalised out of whatever shape the
 * provider's webhook uses. This is the only thing
 * {@see ProviderSubscriptionService} understands — each provider integration
 * (App Store today; Play Store/Stripe/RevenueCat later) translates its own
 * payload into one of these, so the subscription state machine never branches
 * on provider.
 *
 * `originalTransactionId` is the provider's stable id for the whole contract
 * (Apple `originalTransactionId`, Google `purchaseToken` chain, Stripe
 * `subscription` id); `transactionId` identifies this one charge/event.
 */
final readonly class ProviderTransaction
{
    /**
     * @param  string|null  $amount  Decimal string in major units (e.g. "9.99"), or null when the event carries no charge.
     * @param  bool|null  $autoRenews  Null when the event says nothing about renewal — the stored flag is kept.
     * @param  array<string, mixed>  $payload  The provider's raw transaction data, copied onto each receipt.
     * @param  (Model&ProviderNotification)|null  $notification  The raw webhook row this came from, linked from each receipt.
     */
    public function __construct(
        public PaymentProvider $provider,
        public string $originalTransactionId,
        public ?string $transactionId = null,
        public ?string $productId = null,
        public ?CarbonInterface $purchasedAt = null,
        public ?CarbonInterface $expiresAt = null,
        public ?string $amount = null,
        public ?string $currency = null,
        public bool $isTrial = false,
        public ?bool $autoRenews = null,
        public array $payload = [],
        public (Model&ProviderNotification)|null $notification = null,
    ) {}
}
