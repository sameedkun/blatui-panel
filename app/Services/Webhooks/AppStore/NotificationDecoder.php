<?php

namespace App\Services\Webhooks\AppStore;

use App\Exceptions\InvalidWebhookPayloadException;
use App\Models\Webhooks\AppleNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use JsonSerializable;
use Readdle\AppStoreServerAPI\Exception\AppStoreServerNotificationException;
use Readdle\AppStoreServerAPI\ResponseBodyV2;
use RuntimeException;

/**
 * Verifies and decodes an App Store Server Notification V2 request body
 * (`{"signedPayload": "<JWS>"}`) into {@see AppleNotification} attributes.
 * The only class that touches `readdle/app-store-server-api`.
 *
 * Verification is fail-closed: the package skips the root-of-trust check when
 * given no root certificate (any self-signed chain would pass), so a missing
 * Apple Root CA file ({@see RootCertificate::pem()}) is a configuration error
 * here, never a silent downgrade.
 */
class NotificationDecoder
{
    public function __construct(private RootCertificate $rootCertificate) {}

    /**
     * @return array{
     *     notification_type: string,
     *     subtype: string|null,
     *     notification_uuid: string,
     *     version: string,
     *     signed_date: CarbonInterface,
     *     payload: array<string, mixed>,
     *     transaction_info: array<string, mixed>|null,
     *     renewal_info: array<string, mixed>|null,
     *     app_account_token: string|null,
     *     original_transaction_id: string|null,
     *     transaction_id: string|null,
     *     product_id: string|null,
     * }
     *
     * @throws InvalidWebhookPayloadException when the body isn't an Apple-signed notification for this app.
     * @throws RuntimeException when the Apple root certificate isn't configured.
     */
    public function decode(string $rawBody): array
    {
        $rootCertificate = $this->rootCertificate->pem();

        try {
            $body = ResponseBodyV2::createFromRawNotification($rawBody, $rootCertificate);
        } catch (AppStoreServerNotificationException $e) {
            throw new InvalidWebhookPayloadException($e->getMessage(), previous: $e);
        }

        $metadata = $body->getAppMetadata();
        $bundleId = config('services.app_store.bundle_id');

        // Any App Store app's notifications verify against Apple's root — only
        // the bundle id proves this one was meant for us.
        if ($bundleId && $metadata->getBundleId() !== $bundleId) {
            throw new InvalidWebhookPayloadException("Notification is for bundle [{$metadata->getBundleId()}], expected [{$bundleId}].");
        }

        $transaction = $this->toArray($metadata->getTransactionInfo());
        $renewal = $this->toArray($metadata->getRenewalInfo());
        // The package casts an explicit `"subtype": null` to '' — normalise back to null.
        $subtype = $body->getSubtype() ?: null;

        return [
            'notification_type' => $body->getNotificationType(),
            'subtype' => $subtype,
            'notification_uuid' => $body->getNotificationUUID(),
            'version' => $body->getVersion(),
            'signed_date' => Date::createFromTimestampMs($body->getSignedDate()),
            'payload' => [
                'notificationType' => $body->getNotificationType(),
                'subtype' => $subtype,
                'notificationUUID' => $body->getNotificationUUID(),
                'version' => $body->getVersion(),
                'signedDate' => $body->getSignedDate(),
                'data' => array_diff_key($metadata->jsonSerialize(), array_flip(['transactionInfo', 'renewalInfo'])),
                // The original JWS, so a stored row can always be re-verified.
                'signedPayload' => json_decode($rawBody, true)['signedPayload'] ?? null,
            ],
            'transaction_info' => $transaction,
            'renewal_info' => $renewal,
            'app_account_token' => $transaction['appAccountToken'] ?? $renewal['appAccountToken'] ?? null,
            'original_transaction_id' => $transaction['originalTransactionId'] ?? $renewal['originalTransactionId'] ?? null,
            'transaction_id' => $transaction['transactionId'] ?? null,
            'product_id' => $transaction['productId'] ?? $renewal['productId'] ?? null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function toArray(?JsonSerializable $info): ?array
    {
        return $info ? json_decode(json_encode($info), true) : null;
    }
}
