<?php

namespace App\Notifications\Auth;

use App\Mail\Auth\VerifyEmailMail;
use App\Services\Auth\UrlResolver;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

/**
 * Queueable email verification notification. The panel-vs-frontend decision
 * lives entirely in {@see UrlResolver} — this class never chooses it.
 */
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): Mailable
    {
        $url = app(UrlResolver::class)->verificationUrl($notifiable);
        $email = $notifiable->routeNotificationFor('mail') ?? $notifiable->email;

        return (new VerifyEmailMail($url))->to($email);
    }
}
