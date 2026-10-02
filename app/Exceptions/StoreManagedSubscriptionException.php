<?php

namespace App\Exceptions;

use App\Enum\PaymentProvider;
use RuntimeException;

/**
 * An admin tried to assign or change a plan for someone whose live
 * subscription is billed by a store (App Store, Play Store, Stripe, …). The
 * store owns that contract — it would keep billing whatever we did locally,
 * and its next notification would revive the row — so the change is refused.
 */
class StoreManagedSubscriptionException extends RuntimeException
{
    public function __construct(public readonly PaymentProvider $provider)
    {
        parent::__construct("The live subscription is billed by {$provider->label()}; plan changes must be made there.");
    }
}
