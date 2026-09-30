<?php

namespace App\Http\Controllers\Webhooks;

use App\Enum\AppleNotificationSubtype;
use App\Enum\AppleNotificationType;
use App\Exceptions\InvalidWebhookPayloadException;
use App\Http\Controllers\Controller;
use App\Listeners\Webhooks\ProcessAppStoreNotification;
use App\Models\Webhooks\AppleNotification;
use App\Services\Webhooks\AppStore\NotificationDecoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives App Store Server Notifications V2. Only verifies, stores and hands
 * off — the subscription work happens on the queue in
 * {@see ProcessAppStoreNotification}, so Apple gets its 200 immediately.
 *
 * Status codes matter to Apple: anything other than 2xx is retried (up to five
 * times over three days). So an unverifiable body is a 400 (retrying can't
 * fix it), a server-side problem is a 5xx (retrying will, once fixed), and a
 * duplicate or a type we don't know yet is a 200 (nothing to retry).
 */
class AppStoreWebhookController extends Controller
{
    public function handle(Request $request, NotificationDecoder $decoder): JsonResponse
    {
        if (config('services.app_store.verify_url_signature') && ! $request->hasValidRelativeSignature()) {
            Log::channel('webhooks')->warning('App Store: rejected webhook with an invalid URL signature', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        if ($request->getContent() === '') {
            return response()->json(['error' => 'Empty body'], 400);
        }

        try {
            $attributes = $decoder->decode($request->getContent());
        } catch (InvalidWebhookPayloadException $e) {
            Log::channel('webhooks')->warning('App Store: rejected unverifiable notification', [
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Verification failed'], 400);
        }

        // The model casts both columns to enums, so a value Apple added after
        // these enums were written can't be stored — acknowledge it and log it.
        if (! AppleNotificationType::tryFrom($attributes['notification_type'])
            || ($attributes['subtype'] !== null && ! AppleNotificationSubtype::tryFrom($attributes['subtype']))) {
            Log::channel('webhooks')->warning('App Store: unsupported notification type ignored', [
                'notification_uuid' => $attributes['notification_uuid'],
                'notification_type' => $attributes['notification_type'],
                'subtype' => $attributes['subtype'],
                'signed_payload' => $attributes['payload']['signedPayload'],
            ]);

            return response()->json(['status' => 'ignored']);
        }

        $notification = AppleNotification::createOrFirst(
            ['notification_uuid' => $attributes['notification_uuid']],
            $attributes,
        );

        // Apple only redelivers when it didn't get a 200 — e.g. the row was stored
        // but queueing failed — so an unprocessed duplicate is handed off again.
        // Processing is idempotent, so this can never double-apply.
        if (! $notification->wasRecentlyCreated && $notification->processed) {
            return response()->json(['status' => 'duplicate']);
        }

        $notification->redispatch();

        return response()->json(['status' => 'received']);
    }
}
