<?php

namespace App\Jobs\Announcement;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityModule;
use App\Enum\AnnouncementPushStatus;
use App\Models\Announcement;
use App\Services\Announcement\OneSignalService;
use App\Support\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends an {@see Announcement} as a OneSignal push broadcast and records the
 * outcome back onto the row — dispatched from the admin panel (create/edit
 * with "send now", or a manual resend/retry) rather than the scheduler.
 */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $announcementId) {}

    public function handle(OneSignalService $oneSignal): void
    {
        $announcement = Announcement::find($this->announcementId);

        if (! $announcement) {
            return;
        }

        $result = $oneSignal->sendToAll(
            $announcement->title,
            $announcement->message,
            ['announcement_id' => $announcement->id, 'type' => $announcement->type->value],
            $announcement->link,
        );

        if ($result['success']) {
            $announcement->update([
                'push_status' => AnnouncementPushStatus::Sent,
                'push_sent_at' => now(),
                'push_error' => null,
                'onesignal_notification_id' => $result['id'] ?? null,
            ]);

            ActivityLogger::log(ActivityModule::Announcement, ActivityAction::Sent, $announcement, [
                'recipients' => $result['recipients'] ?? 0,
            ], causer: null, context: ActivityContext::Queue);
        } else {
            $announcement->update([
                'push_status' => AnnouncementPushStatus::Failed,
                'push_error' => $result['error'] ?? 'Unknown error',
            ]);

            ActivityLogger::log(ActivityModule::Announcement, ActivityAction::Failed, $announcement, [
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
