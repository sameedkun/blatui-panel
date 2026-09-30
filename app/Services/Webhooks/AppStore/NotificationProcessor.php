<?php

namespace App\Services\Webhooks\AppStore;

use App\Enum\AppleNotificationSubtype as Subtype;
use App\Enum\AppleNotificationType as Type;
use App\Enum\CancelledBy;
use App\Enum\PaymentProvider;
use App\Listeners\Webhooks\ProcessAppStoreNotification;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Webhooks\AppleNotification;
use App\Services\Subscription\ProviderSubscriptionService;
use App\Support\Subscription\ProviderTransaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Applies one stored {@see AppleNotification} to the subscription it concerns.
 * Purely a translation layer: Apple's notification types/subtypes and
 * JWS field names in, {@see ProviderSubscriptionService} calls out — no
 * subscription state is written here directly. Run by the queued listener
 * {@see ProcessAppStoreNotification}.
 *
 * A notification is marked `processed` once it has been applied, or when it is
 * informational and needs nothing. It is deliberately left unprocessed (and
 * logged to the `webhooks` channel) when it can't be applied yet — no account
 * matches its `appAccountToken`, its product id isn't mapped to a plan price,
 * or it concerns a contract we have never seen — so an admin can fix the cause
 * and use "Reprocess" from the Webhook Notifications page.
 */
class NotificationProcessor
{
    /**
     * Types that change subscription state. A state-only notification is
     * skipped when a newer one of these has already been applied to the same
     * contract — Apple retries deliveries, so they can arrive out of order.
     */
    private const STATE_TYPES = [
        Type::Subscribed, Type::DidRenew, Type::DidChangeRenewalPref, Type::DidChangeRenewalStatus,
        Type::DidFailToRenew, Type::GracePeriodExpired, Type::Expired, Type::Refund,
        Type::RefundReversed, Type::RenewalExtended, Type::Revoke, Type::OfferRedeemed,
    ];

    /** Types that only toggle state (no charge) and so may be superseded. */
    private const SUPERSEDABLE_TYPES = [
        Type::DidChangeRenewalStatus, Type::DidFailToRenew, Type::GracePeriodExpired, Type::Expired,
    ];

    public function __construct(private ProviderSubscriptionService $subscriptions) {}

    /**
     * Notifications for the same contract are applied one at a time, so two
     * workers can't both pass an idempotency check before either commits.
     */
    public function process(AppleNotification $notification): void
    {
        if (! $notification->original_transaction_id) {
            $this->apply($notification);

            return;
        }

        Cache::lock('app-store-notification:'.$notification->original_transaction_id, 60)
            ->block(15, fn () => $this->apply($notification->refresh()));
    }

    private function apply(AppleNotification $notification): void
    {
        $environment = $notification->environment() ?? ($notification->transaction_info['environment'] ?? null);

        if (! in_array($environment, (array) config('services.app_store.environments'), true)) {
            $this->logger()->info('App Store: notification ignored for environment', $this->context($notification));
            $this->markProcessed($notification);

            return;
        }

        if ($this->isSuperseded($notification)) {
            $this->logger()->info('App Store: superseded by a newer notification', $this->context($notification));
            $this->markProcessed($notification);

            return;
        }

        $applied = match ($notification->notification_type) {
            Type::Subscribed => $this->subscribed($notification),
            Type::DidRenew => $this->renewed($notification),
            Type::DidChangeRenewalPref => $this->renewalPreferenceChanged($notification),
            Type::DidChangeRenewalStatus => $this->renewalStatusChanged($notification),
            Type::DidFailToRenew => $this->failedToRenew($notification),
            Type::GracePeriodExpired => $this->withSubscription($notification, fn (Subscription $s, ProviderTransaction $t) => $this->subscriptions->paymentFailed($s, null, $t)),
            Type::Expired => $this->expired($notification),
            Type::Refund => $this->refunded($notification),
            Type::RefundReversed => $this->withSubscription($notification, fn (Subscription $s, ProviderTransaction $t) => $this->subscriptions->reverseRefund($s, $t)),
            Type::RenewalExtended => $this->withSubscription($notification, fn (Subscription $s, ProviderTransaction $t) => $this->subscriptions->extend($s, $t)),
            Type::Revoke => $this->withSubscription($notification, fn (Subscription $s, ProviderTransaction $t) => $this->subscriptions->revoke($s, $t, 'Family Sharing access revoked')),
            Type::OfferRedeemed => $this->offerRedeemed($notification),
            // Informational — nothing to change: TEST, CONSUMPTION_REQUEST,
            // REFUND_DECLINED, PRICE_INCREASE, RENEWAL_EXTENSION, ONE_TIME_CHARGE,
            // EXTERNAL_PURCHASE_TOKEN.
            default => true,
        };

        if ($applied) {
            $this->markProcessed($notification);
        }
    }

    /** SUBSCRIBED (INITIAL_BUY / RESUBSCRIBE) — a new contract. */
    private function subscribed(AppleNotification $notification): bool
    {
        if (! $transaction = $this->transaction($notification)) {
            return $this->skip($notification, 'no transaction info');
        }

        if (! $user = $this->owner($notification, $transaction)) {
            return $this->skip($notification, 'no account matches the appAccountToken');
        }

        if (! $price = $this->subscriptions->resolvePrice(PaymentProvider::AppStore, $transaction->productId)) {
            return $this->skip($notification, 'product id is not mapped to a plan price');
        }

        $this->subscriptions->start($user, $price, $transaction);

        return true;
    }

    /** DID_RENEW — a renewal charge, possibly onto a new product (downgrade/crossgrade taking effect). */
    private function renewed(AppleNotification $notification): bool
    {
        $transaction = $this->transaction($notification);
        $subscription = $this->subscriptions->findSubscription(PaymentProvider::AppStore, $transaction?->originalTransactionId);

        // The SUBSCRIBED delivery never reached us (or was never applied) — start from here.
        if (! $subscription) {
            return $this->subscribed($notification);
        }

        $price = $this->subscriptions->resolvePrice(PaymentProvider::AppStore, $transaction->productId);

        if ($price && $price->id !== $subscription->plan_price_id) {
            $this->subscriptions->changePlan($subscription, $price, $transaction);
        } else {
            $this->subscriptions->renew($subscription, $transaction, recovered: $notification->subtype === Subtype::BillingRecovery);
        }

        return true;
    }

    /** DID_CHANGE_RENEWAL_PREF — UPGRADE applies now; DOWNGRADE waits for the next DID_RENEW. */
    private function renewalPreferenceChanged(AppleNotification $notification): bool
    {
        return $notification->subtype === Subtype::Upgrade ? $this->upgraded($notification) : true;
    }

    private function upgraded(AppleNotification $notification): bool
    {
        return $this->withSubscription($notification, function (Subscription $subscription, ProviderTransaction $transaction) use ($notification): bool {
            $price = $this->subscriptions->resolvePrice(PaymentProvider::AppStore, $transaction->productId);

            if (! $price) {
                return $this->skip($notification, 'product id is not mapped to a plan price');
            }

            if ($price->id !== $subscription->plan_price_id) {
                $this->subscriptions->changePlan($subscription, $price, $transaction);
            }

            return true;
        });
    }

    /** DID_CHANGE_RENEWAL_STATUS — AUTO_RENEW_ENABLED / AUTO_RENEW_DISABLED. */
    private function renewalStatusChanged(AppleNotification $notification): bool
    {
        return $this->withSubscription($notification, function (Subscription $subscription, ProviderTransaction $transaction) use ($notification): void {
            $enabled = match ($notification->subtype) {
                Subtype::AutoRenewEnabled => true,
                Subtype::AutoRenewDisabled => false,
                default => (bool) $transaction->autoRenews,
            };

            $enabled
                ? $this->subscriptions->enableAutoRenew($subscription, $transaction)
                : $this->subscriptions->disableAutoRenew($subscription, $transaction);
        });
    }

    /** DID_FAIL_TO_RENEW — GRACE_PERIOD keeps access until `gracePeriodExpiresDate`; no subtype stops it. */
    private function failedToRenew(AppleNotification $notification): bool
    {
        return $this->withSubscription($notification, function (Subscription $subscription, ProviderTransaction $transaction) use ($notification): void {
            $graceEndsAt = $notification->subtype === Subtype::GracePeriod
                ? $this->date($notification->renewal_info['gracePeriodExpiresDate'] ?? null)
                : null;

            $this->subscriptions->paymentFailed($subscription, $graceEndsAt, $transaction);
        });
    }

    /** EXPIRED — VOLUNTARY / BILLING_RETRY / PRICE_INCREASE / PRODUCT_NOT_FOR_SALE. */
    private function expired(AppleNotification $notification): bool
    {
        [$reason, $cancelledBy] = match ($notification->subtype) {
            Subtype::Voluntary => ['voluntary', CancelledBy::User],
            Subtype::PriceIncrease => ['price_increase_declined', CancelledBy::User],
            Subtype::BillingRetry => ['billing_retry_ended', CancelledBy::System],
            Subtype::ProductNotForSale => ['product_not_for_sale', CancelledBy::System],
            default => ['expired', CancelledBy::System],
        };

        return $this->withSubscription($notification, fn (Subscription $s, ProviderTransaction $t) => $this->subscriptions->expire($s, $t, $reason, $cancelledBy));
    }

    /** REFUND — `revocationReason` 1 means an issue with the app, 0 anything else. */
    private function refunded(AppleNotification $notification): bool
    {
        return $this->withSubscription($notification, function (Subscription $subscription, ProviderTransaction $transaction): void {
            $reason = match ($transaction->payload['revocationReason'] ?? null) {
                1 => 'Issue with the app',
                0 => 'Other reason',
                default => null,
            };

            $this->subscriptions->refund($subscription, $transaction, $this->date($transaction->payload['revocationDate'] ?? null), $reason);
        });
    }

    /**
     * OFFER_REDEEMED — INITIAL_BUY/RESUBSCRIBE start a contract, UPGRADE applies
     * now; DOWNGRADE and a plain offer on the current plan change nothing yet.
     */
    private function offerRedeemed(AppleNotification $notification): bool
    {
        return match ($notification->subtype) {
            Subtype::InitialBuy, Subtype::Resubscribe => $this->subscribed($notification),
            Subtype::Upgrade => $this->upgraded($notification),
            default => true,
        };
    }

    /**
     * Runs `$apply` against the contract this notification belongs to. Returns
     * false (leave unprocessed) when there is no such contract yet.
     *
     * @param  callable(Subscription, ProviderTransaction): mixed  $apply
     */
    private function withSubscription(AppleNotification $notification, callable $apply): bool
    {
        $transaction = $this->transaction($notification);
        $subscription = $this->subscriptions->findSubscription(PaymentProvider::AppStore, $transaction?->originalTransactionId);

        if (! $subscription) {
            return $this->skip($notification, 'no subscription recorded for this original transaction');
        }

        return $apply($subscription, $transaction) !== false;
    }

    private function transaction(AppleNotification $notification): ?ProviderTransaction
    {
        $info = $notification->transaction_info;

        if (empty($info['originalTransactionId'])) {
            return null;
        }

        $autoRenewStatus = $notification->renewal_info['autoRenewStatus'] ?? null;

        return new ProviderTransaction(
            provider: PaymentProvider::AppStore,
            originalTransactionId: (string) $info['originalTransactionId'],
            transactionId: isset($info['transactionId']) ? (string) $info['transactionId'] : null,
            productId: $info['productId'] ?? null,
            purchasedAt: $this->date($info['purchaseDate'] ?? null),
            expiresAt: $this->date($info['expiresDate'] ?? null),
            // Apple sends `price` in milliunits: 9990 = 9.99.
            amount: isset($info['price']) ? number_format($info['price'] / 1000, 2, '.', '') : null,
            currency: $info['currency'] ?? null,
            isTrial: ($info['offerDiscountType'] ?? null) === 'FREE_TRIAL',
            autoRenews: $autoRenewStatus === null ? null : (int) $autoRenewStatus === 1,
            payload: $info,
            notification: $notification,
        );
    }

    /**
     * The account a purchase belongs to: the `appAccountToken` the client set
     * (see {@see User::appAccountToken()}), falling back to whoever owns the
     * contract already — covers purchases made before the token was set and
     * guests merged into another account.
     */
    private function owner(AppleNotification $notification, ProviderTransaction $transaction): ?User
    {
        return User::findByAppAccountToken($notification->app_account_token)
            ?? $this->subscriptions
                ->findSubscription(PaymentProvider::AppStore, $transaction->originalTransactionId)
                ?->user()->withTrashed()->first();
    }

    private function isSuperseded(AppleNotification $notification): bool
    {
        if (! in_array($notification->notification_type, self::SUPERSEDABLE_TYPES, true) || ! $notification->original_transaction_id) {
            return false;
        }

        return AppleNotification::query()
            ->whereKeyNot($notification->getKey())
            ->where('original_transaction_id', $notification->original_transaction_id)
            ->where('processed', true)
            ->whereIn('notification_type', self::STATE_TYPES)
            ->where('signed_date', '>', $notification->signed_date)
            ->exists();
    }

    private function markProcessed(AppleNotification $notification): void
    {
        $notification->forceFill(['processed' => true, 'processed_at' => now()])->save();
    }

    private function skip(AppleNotification $notification, string $reason): bool
    {
        $this->logger()->warning("App Store: left unprocessed — {$reason}", $this->context($notification));

        return false;
    }

    private function date(mixed $milliseconds): ?CarbonInterface
    {
        return is_numeric($milliseconds) ? Date::createFromTimestampMs((int) $milliseconds) : null;
    }

    /** @return array<string, mixed> */
    private function context(AppleNotification $notification): array
    {
        return [
            'notification_id' => $notification->id,
            'notification_uuid' => $notification->notification_uuid,
            'notification_type' => $notification->notification_type->value,
            'subtype' => $notification->subtype?->value,
            'original_transaction_id' => $notification->original_transaction_id,
            'product_id' => $notification->product_id,
        ];
    }

    private function logger(): LoggerInterface
    {
        return Log::channel('webhooks');
    }
}
