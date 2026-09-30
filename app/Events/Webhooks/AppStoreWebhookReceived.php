<?php

namespace App\Events\Webhooks;

use App\Enum\AppleNotificationSubtype;
use App\Enum\AppleNotificationType;
use App\Http\Controllers\Webhooks\AppStoreWebhookController;
use App\Listeners\Webhooks\ProcessAppStoreNotification;
use App\Models\Webhooks\AppleNotification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once a raw Apple Server Notification V2 has been verified and stored
 * as an {@see AppleNotification} row — by {@see AppStoreWebhookController} for
 * a live delivery, and by the admin "Reprocess" action
 * ({@see AppleNotification::redispatch()}) for a replay. Both go through
 * {@see AppleNotification::redispatch()}, so the queued
 * {@see ProcessAppStoreNotification} listener handles them identically.
 */
class AppStoreWebhookReceived
{
    use Dispatchable, SerializesModels;

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
