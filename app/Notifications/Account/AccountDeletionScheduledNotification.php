<?php

namespace App\Notifications\Account;

use App\Mail\Account\AccountDeletionScheduledMail;
use App\Services\Account\DeletionService;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Queueable notification sent when {@see DeletionService}
 * marks an account for deletion (either self- or admin-initiated).
 */
class AccountDeletionScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CarbonInterface $purgesAt, public bool $initiatedByAdmin) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): Mailable
    {
        $email = $notifiable->routeNotificationFor('mail') ?? $notifiable->email;

        return (new AccountDeletionScheduledMail($this->purgesAt, $this->initiatedByAdmin))->to($email);
    }
}
