<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Bumps `password_changed_at` whenever a password is changed via Laravel's
 * password-broker reset flow. Wired automatically by event discovery (this
 * class type-hints the event) — any future code path that reuses the broker
 * and fires this event gets the timestamp updated for free, with no need to
 * duplicate the assignment at every call site.
 */
class TouchPasswordChangedAt
{
    public function handle(PasswordReset $event): void
    {
        $user = $event->user;

        if ($user instanceof User) {
            $user->update(['password_changed_at' => now()]);
        }
    }
}
