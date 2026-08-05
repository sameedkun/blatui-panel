<?php

namespace App\Enum;

/**
 * Apple's App Store Server Notifications V2 `notificationType` vocabulary.
 * Closed vocabulary — Apple owns it, adding a new value is a code change.
 *
 * @see https://developer.apple.com/documentation/appstoreservernotifications/notificationtype
 */
enum AppleNotificationType: string
{
    case ConsumptionRequest = 'CONSUMPTION_REQUEST';
    case DidChangeRenewalPref = 'DID_CHANGE_RENEWAL_PREF';
    case DidChangeRenewalStatus = 'DID_CHANGE_RENEWAL_STATUS';
    case DidFailToRenew = 'DID_FAIL_TO_RENEW';
    case DidRenew = 'DID_RENEW';
    case Expired = 'EXPIRED';
    case ExternalPurchaseToken = 'EXTERNAL_PURCHASE_TOKEN';
    case GracePeriodExpired = 'GRACE_PERIOD_EXPIRED';
    case OfferRedeemed = 'OFFER_REDEEMED';
    case OneTimeCharge = 'ONE_TIME_CHARGE';
    case PriceIncrease = 'PRICE_INCREASE';
    case Refund = 'REFUND';
    case RefundDeclined = 'REFUND_DECLINED';
    case RefundReversed = 'REFUND_REVERSED';
    case RenewalExtended = 'RENEWAL_EXTENDED';
    case RenewalExtension = 'RENEWAL_EXTENSION';
    case Revoke = 'REVOKE';
    case Subscribed = 'SUBSCRIBED';
    case Test = 'TEST';

    public function label(): string
    {
        return __("enums.apple_notification_type.{$this->name}");
    }
}
