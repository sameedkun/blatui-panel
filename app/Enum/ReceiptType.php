<?php

namespace App\Enum;

use App\Models\SubscriptionReceipt;

/**
 * The kind of provider event a {@see SubscriptionReceipt} records — the
 * provider-agnostic ledger vocabulary every webhook integration translates its
 * own event names into. Closed vocabulary — adding a new type is a code change.
 *
 * Money-bearing types (Initial, Renewal, PlanChange, Refund, RefundReversed)
 * are keyed on the provider's transaction id; the rest record a state change.
 */
enum ReceiptType: string
{
    case Initial = 'initial';
    case Renewal = 'renewal';
    case Restore = 'restore';
    case Refund = 'refund';
    case Cancellation = 'cancellation';
    case PlanChange = 'plan_change';
    case BillingFailure = 'billing_failure';
    case Expiration = 'expiration';
    case Reactivation = 'reactivation';
    case RefundReversed = 'refund_reversed';
    case Extension = 'extension';
    case Revocation = 'revocation';

    public function label(): string
    {
        return __("enums.receipt_type.{$this->name}");
    }
}
