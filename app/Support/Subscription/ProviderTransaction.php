<?php

namespace App\Support\Subscription;

use App\Contracts\ProviderNotification;
use App\Enum\PaymentProvider;
use App\Services\Subscription\ProviderSubscriptionService;
use App\Support\Money\Money;
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
 * `subscription` id); `transactionId` identifies this one charge.
 *
 * `amount` is already in canonical {@see Money} — the integration converts
 * its own unit (Apple milliunits, Google micros, …) before building this.
 */
final readonly class ProviderTransaction
{
    /**
     * @param  Money|null  $amount  What this transaction charged, or null when the event carries no price. For a refund
     *                              event: the **total** refunded on this transaction so far, as the provider reports it
     *                              (the service records only the part not already on the ledger).
     * @param  string|null  $eventKey  Distinguishes repeated events of one kind on the same transaction — e.g. each
     *                                 successive partial refund. Stable across redeliveries of the same event.
     * @param  bool|null  $autoRenews  Null when the event says nothing about renewal — the stored flag is kept.
     * @param  array<string, mixed>  $payload  The provider's raw transaction data, copied onto each ledger row.
     * @param  (Model&ProviderNotification)|null  $notification  The raw webhook row this came from, linked from each ledger row.
     */
    public function __construct(
        public PaymentProvider $provider,
        public string $originalTransactionId,
        public ?string $transactionId = null,
        public ?string $productId = null,
        public ?CarbonInterface $purchasedAt = null,
        public ?CarbonInterface $expiresAt = null,
        public ?Money $amount = null,
        public bool $isTrial = false,
        public ?bool $autoRenews = null,
        public array $payload = [],
        public (Model&ProviderNotification)|null $notification = null,
        public ?string $eventKey = null,
    ) {}

    /** A copy carrying a different amount. */
    public function withAmount(?Money $amount): self
    {
        return $this->copy(['amount' => $amount]);
    }

    /** A copy describing one refund event: the provider's cumulative refunded total and the event's key. */
    public function asRefund(?Money $refundedTotal, ?string $eventKey): self
    {
        return $this->copy(['amount' => $refundedTotal, 'eventKey' => $eventKey]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function copy(array $overrides): self
    {
        return new self(...[...get_object_vars($this), ...$overrides]);
    }
}
