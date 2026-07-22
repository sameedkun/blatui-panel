<?php

namespace App\Notifications\Auth;

use App\Mail\Auth\ResetPasswordMail;
use App\Services\Auth\UrlResolver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

/**
 * Queueable password reset notification. The panel-vs-frontend decision
 * lives entirely in {@see UrlResolver} — this class never chooses it.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): Mailable
    {
        $url = app(UrlResolver::class)->passwordResetUrl($notifiable, $this->token);
        $count = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $email = $notifiable->routeNotificationFor('mail') ?? $notifiable->email;

        return (new ResetPasswordMail($url, $count))->to($email);
    }
}
