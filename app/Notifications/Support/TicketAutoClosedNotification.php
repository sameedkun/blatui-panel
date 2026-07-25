<?php

namespace App\Notifications\Support;

use App\Mail\Support\TicketAutoClosedMail;
use App\Models\Ticket;
use App\Services\TicketLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Queueable notification sent when {@see TicketLifecycleService}
 * auto-closes an inactive ticket.
 */
class TicketAutoClosedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Ticket $ticket, protected int $inactiveDays) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): Mailable
    {
        $email = $notifiable->routeNotificationFor('mail') ?? $notifiable->email;

        return (new TicketAutoClosedMail($this->ticket, $this->inactiveDays))->to($email);
    }
}
