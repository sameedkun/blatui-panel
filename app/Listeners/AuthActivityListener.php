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
use Illuminate\Auth\Events\Verified;
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

        $user = $email ? User::where('email', $email)->first() : null;

        // Guests are never logged for auth activity, so only attach the subject
        // (and let it surface on the profile's Activity tab) for staff/app users.
        $loggableUser = $user && ! $user->isGuest() ? $user : null;

        ActivityLogger::log(
            $loggableUser ? $this->moduleFor($loggableUser) : ActivityModule::User,
            ActivityAction::Failed,
            $loggableUser,
            properties: [
                'email' => $email,
                'reason' => $user ? 'Invalid password' : 'Account not found',
            ],
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

    /**
     * Covers both self-service verification (the emailed signed link — including
     * an unauthenticated click, since a valid signature is proof enough) and
     * admin-initiated verification (Users\Show::verifyEmailManually), attributing
     * the causer correctly for each.
     */
    public function handleVerified(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->isGuest()) {
            return;
        }

        $authUser = auth()->user();
        $isAdminInitiated = $authUser instanceof User && $authUser->isNot($user);

        ActivityLogger::log(
            $this->moduleFor($user),
            ActivityAction::Verified,
            $user,
            properties: ['initiated_by' => $isAdminInitiated ? 'admin' : 'self'],
            causer: $isAdminInitiated ? $authUser : $user,
            logName: ActivityLogName::Authentication,
        );
    }

    private function moduleFor(User $user): ActivityModule
    {
        return $user->isStaff() ? ActivityModule::Staff : ActivityModule::User;
    }
}
