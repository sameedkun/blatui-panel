<?php

namespace App\Enum;

use App\Models\Subscription;

/**
 * The payment rail a {@see PlanPrice}'s provider mapping, a {@see Subscription},
 * or a {@see SubscriptionReceipt} is tied to. `Local` means no external payment
 * provider is involved (manually granted/admin-managed subscription). Closed
 * vocabulary — adding a new provider is a code change.
 */
enum PaymentProvider: string
{
    case Local = 'local';
    case Stripe = 'stripe';
    case AppStore = 'appstore';
    case PlayStore = 'playstore';
    case Oxapay = 'oxapay';
    case RevenueCat = 'revenuecat';

    public function label(): string
    {
        return __("enums.payment_provider.{$this->name}");
    }

    /**
     * Where a customer fixes their payment method / manages the subscription,
     * for stores that own billing. Null when there's no customer-facing page.
     */
    public function manageSubscriptionUrl(): ?string
    {
        return match ($this) {
            self::AppStore => 'https://apps.apple.com/account/subscriptions',
            self::PlayStore => 'https://play.google.com/store/account/subscriptions',
            default => null,
        };
    }
}
