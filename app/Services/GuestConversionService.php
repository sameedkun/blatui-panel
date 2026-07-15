<?php

namespace App\Services;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\UserType;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Converts a guest account into a real app user in place — same row, same id,
 * same activity history. Never creates a new record.
 */
class GuestConversionService
{
    /**
     * The guest converts their own account (future public API). The guest
     * chooses their own password directly, so no reset flow is needed.
     */
    public function convertBySelf(User $guest, string $email, string $password, ?string $name = null): void
    {
        $this->assertGuestUser($guest);
        $this->assertNotBanned($guest);
        $this->validateEmail($email, $guest);

        $this->convert($guest, $email, $name, Hash::make($password), 'self');
    }

    /**
     * An admin converts a guest on the account's behalf. The admin never sets
     * a real password directly — a random, unusable one is generated instead;
     * the real owner sets their own credentials via the password-reset flow.
     */
    public function convertByAdmin(User $guest, string $email, ?string $name = null): void
    {
        $this->assertGuestUser($guest);
        $this->assertNotBanned($guest);
        $this->validateEmail($email, $guest);

        $this->convert($guest, $email, $name, Hash::make(Str::random(64)), 'admin');

        // TODO: Password::sendResetLink(['email' => $guest->email]) — deferred
        // until the future API/client defines a `password.reset` route for the
        // ResetPassword notification to link to (none exists yet; this admin
        // panel only has staff login, not a self-service auth surface).
    }

    private function convert(User $guest, string $email, ?string $name, string $hashedPassword, string $initiatedBy): void
    {
        $oldEmail = $guest->email;

        $guest->forceFill([
            'type' => UserType::App,
            'email' => $email,
            'name' => $name ?: $guest->name,
            'password' => $hashedPassword,
            'email_verified_at' => null,
        ])->save();

        ActivityLogger::log(ActivityModule::Guest, ActivityAction::Converted, $guest, [
            'initiated_by' => $initiatedBy,
            'old_email' => $oldEmail,
            'new_email' => $email,
        ]);

        // TODO: $guest->sendEmailVerificationNotification() — deferred until
        // User implements MustVerifyEmail and the future API/client defines a
        // `verification.verify` route for the VerifyEmail notification to link to.
    }

    private function assertGuestUser(User $guest): void
    {
        if ($guest->type !== UserType::Guest) {
            throw new InvalidArgumentException('Only guest accounts can be converted.');
        }
    }

    private function assertNotBanned(User $guest): void
    {
        if ($guest->isBanned()) {
            throw new InvalidArgumentException('A banned guest account cannot be converted.');
        }
    }

    private function validateEmail(string $email, User $guest): void
    {
        Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($guest->id)],
        ])->validate();
    }
}
