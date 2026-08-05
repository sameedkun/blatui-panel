<?php

namespace App\Enum;

use App\Models\Webhooks\AppleNotification;

/**
 * Apple's App Store Server Notifications V2 `subtype` vocabulary — further
 * qualifies a {@see AppleNotificationType}. Not every notification type
 * carries a subtype, so this is always nullable on {@see AppleNotification}.
 * A handful of raw values (e.g. `ACCEPTED`, `FAILURE`) are reused across
 * different notification types by Apple itself — the case name here reflects
 * only the raw string, not which type it paired with on a given row.
 * Closed vocabulary — Apple owns it, adding a new value is a code change.
 *
 * @see https://developer.apple.com/documentation/appstoreservernotifications/subtype
 */
enum AppleNotificationSubtype: string
{
    case InitialBuy = 'INITIAL_BUY';
    case Resubscribe = 'RESUBSCRIBE';
    case Downgrade = 'DOWNGRADE';
    case Upgrade = 'UPGRADE';
    case AutoRenewEnabled = 'AUTO_RENEW_ENABLED';
    case AutoRenewDisabled = 'AUTO_RENEW_DISABLED';
    case Voluntary = 'VOLUNTARY';
    case BillingRetry = 'BILLING_RETRY';
    case PriceIncrease = 'PRICE_INCREASE';
    case GracePeriod = 'GRACE_PERIOD';
    case BillingRecovery = 'BILLING_RECOVERY';
    case Pending = 'PENDING';
    case Accepted = 'ACCEPTED';
    case ProductNotForSale = 'PRODUCT_NOT_FOR_SALE';
    case Unreported = 'UNREPORTED';
    case Failure = 'FAILURE';

    public function label(): string
    {
        return __("enums.apple_notification_subtype.{$this->name}");
    }
}
