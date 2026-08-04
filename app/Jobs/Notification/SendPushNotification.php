<?php

namespace App\Jobs\Notification;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityModule;
use App\Enum\NotificationPushStatus;
use App\Models\Notification;
use App\Services\Notification\OneSignalService;
use App\Support\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a {@see Notification} as a OneSignal push broadcast and records the
 * outcome back onto the row — dispatched from the admin panel (create/edit
 * with "send now", or a manual resend/retry) rather than the scheduler.
 */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $notificationId) {}

    public function handle(OneSignalService $oneSignal): void
    {
        $notification = Notification::find($this->notificationId);

        if (! $notification) {
            return;
        }

        $result = $oneSignal->sendToAll(
            $notification->title,
            $notification->message,
            ['notification_id' => $notification->id, 'type' => $notification->type->value],
            $notification->link,
        );

        if ($result['success']) {
            $notification->update([
                'push_status' => NotificationPushStatus::Sent,
                'push_sent_at' => now(),
                'push_error' => null,
                'onesignal_notification_id' => $result['id'] ?? null,
            ]);

            ActivityLogger::log(ActivityModule::Notification, ActivityAction::Sent, $notification, [
                'recipients' => $result['recipients'] ?? 0,
            ], causer: null, context: ActivityContext::Queue);
        } else {
            $notification->update([
                'push_status' => NotificationPushStatus::Failed,
                'push_error' => $result['error'] ?? 'Unknown error',
            ]);

            ActivityLogger::log(ActivityModule::Notification, ActivityAction::Failed, $notification, [
                'error' => $result['error'] ?? 'Unknown error',
            ], causer: null, context: ActivityContext::Queue);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: SendPushNotification', [
            'job' => self::class,
            'exception' => $exception,
        ]);
    }
}
