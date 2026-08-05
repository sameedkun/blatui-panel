<?php

namespace App\Events\Webhooks;

use App\Enum\AppleNotificationSubtype;
use App\Enum\AppleNotificationType;
use App\Models\Webhooks\AppleNotification;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once a raw Apple Server Notification V2 has been decoded and stored
 * as an {@see AppleNotification} row. No listener is wired up yet — the
 * actual subscription-state processing logic hangs off this event once it
 * exists. The admin "Reprocess" action ({@see AppleNotification::redispatch()})
 * re-fires this same event from the stored row, so a listener added later
 * handles the original webhook and an admin-triggered replay identically.
 */
class AppStoreWebhookReceived
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>|null  $transactionInfo  Decoded signedTransactionInfo.
     * @param  array<string, mixed>|null  $renewalInfo  Decoded signedRenewalInfo.
     * @param  array<string, mixed>  $responseBodyV2  The full decoded notification payload.
     */
    public function __construct(
        public readonly AppleNotificationType $notificationType,
        public readonly ?AppleNotificationSubtype $subtype,
        public readonly ?array $transactionInfo,
        public readonly ?array $renewalInfo,
        public readonly array $responseBodyV2,
        public readonly AppleNotification $appleNotification,
    ) {}
}
