<?php

namespace App\Notifications\Billing;

use App\Enum\PaymentProvider;
use App\Mail\Billing\PaymentFailedMail;
use App\Services\Subscription\ProviderSubscriptionService;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Queueable notification sent when {@see ProviderSubscriptionService::paymentFailed()}
 * moves a subscription into grace or pauses it after a failed renewal charge.
 */
class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $planName,
        public PaymentProvider $provider,
        public ?CarbonInterface $graceEndsAt = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): Mailable
    {
        $email = $notifiable->routeNotificationFor('mail') ?? $notifiable->email;

        return (new PaymentFailedMail($this->planName, $this->provider, $this->graceEndsAt))->to($email);
    }
}
