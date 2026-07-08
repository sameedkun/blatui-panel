<?php

namespace App\Listeners;

use App\Enum\ActivityAction;
use App\Enum\ActivityLogName;
use App\Enum\ActivityModule;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Records privileged authentication activity under the `authentication`
 * category. The handler methods are wired automatically by Laravel's event
 * discovery (each type-hints its event), so no manual registration is needed.
 *
 * Guests are never logged; failed logins are rate-limited (and do no DB lookup)
 * so credential-stuffing cannot flood the audit trail.
 */
class AuthActivityListener
{
    public function handleLogin(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->isGuest()) {
            return;
        }

        ActivityLogger::log(
            $this->moduleFor($user),
            ActivityAction::Login,
            $user,
            causer: $user,
            logName: ActivityLogName::Authentication,
        );
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        $key = 'failed-login-log:'.Str::lower((string) $email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        RateLimiter::hit($key, 60);

        ActivityLogger::log(
            ActivityModule::User,
            ActivityAction::Failed,
            properties: ['email' => $email],
            causer: null,
            logName: ActivityLogName::Authentication,
        );
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->isGuest()) {
            return;
        }

        ActivityLogger::log(
            $this->moduleFor($user),
            ActivityAction::PasswordReset,
            $user,
            causer: $user,
            logName: ActivityLogName::Authentication,
        );
    }

    private function moduleFor(User $user): ActivityModule
    {
        return $user->isStaff() ? ActivityModule::Staff : ActivityModule::User;
    }
}
