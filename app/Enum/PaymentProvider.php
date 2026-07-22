<?php

namespace App\Enum;

use App\Models\Subscription;
use Illuminate\Support\Str;

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

    public function label(): string
    {
        return Str::headline($this->value);
    }
}
