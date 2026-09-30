<?php

namespace App\Listeners\Webhooks;

use App\Events\Webhooks\AppStoreWebhookReceived;
use App\Services\Webhooks\AppStore\NotificationProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies an App Store notification to its subscription, off the request so
 * the webhook can answer Apple immediately. Wired by event discovery — do not
 * also register it manually. Fires for both a live delivery and an admin
 * "Reprocess", so both go through exactly the same code.
 *
 * Processing is idempotent (see {@see NotificationProcessor}), so a retry after
 * a transient failure can't double-apply anything.
 */
class ProcessAppStoreNotification implements ShouldQueue
{
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(private NotificationProcessor $processor) {}

    public function handle(AppStoreWebhookReceived $event): void
    {
        $this->processor->process($event->appleNotification);
    }

    public function failed(AppStoreWebhookReceived $event, Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: ProcessAppStoreNotification', [
            'job' => self::class,
            'notification_id' => $event->appleNotification->id,
            'exception' => $exception,
        ]);
    }
}
