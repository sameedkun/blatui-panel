<?php

namespace App\Services\Account;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\UserType;
use App\Exceptions\ProviderEmailUnverifiedException;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class GuestConversionService
{
    public function __construct(
        private readonly MergeService $merger,
    ) {}

    public function convertBySelf(User $guest, string $email, string $password, ?string $name = null): User
    {
        $this->assertGuestUser($guest);
        $this->assertNotBanned($guest);
        $this->validateEmail($email, $guest);

        $this->convert($guest, $email, $name, Hash::make($password), 'self');

        return $guest->fresh();
    }

    public function convertByAdmin(User $guest, string $email, ?string $name = null, bool $markEmailVerified = false): User
    {
        $this->assertGuestUser($guest);
        $this->assertNotBanned($guest);
        $this->validateEmail($email, $guest);

        $this->convert($guest, $email, $name, Hash::make(Str::random(64)), 'admin', $markEmailVerified);

        // Always sent regardless of $markEmailVerified — the admin never sets
        // (or sees) the real password either way.
        Password::sendResetLink(['email' => $guest->email]);

        return $guest->fresh();
    }

    /**
     * Google id: string, email: string, emailVerified: bool, name: ?string
     */
    public function convertWithGoogle(User $guest, string $googleId, string $email, bool $emailVerified, ?string $name = null): User
    {
        return $this->convertWithProvider($guest, 'google', $googleId, $email, $emailVerified, $name);
    }

    public function convertWithApple(User $guest, string $appleId, string $email, bool $emailVerified, ?string $name = null): User
    {
        return $this->convertWithProvider($guest, 'apple', $appleId, $email, $emailVerified, $name);
    }

    /**
     * Admin merges a guest into an account identified as the same person
     * (support case). Requires a reason; fully traceable in the audit log.
     */
    public function mergeByAdmin(User $guest, User $destination, string $reason): User
    {
        $this->assertGuestUser($guest);
        $this->assertNotBanned($guest);

        return $this->merger->mergeByAdmin($guest, $destination, $reason);
    }

    /**
     * @throws ProviderEmailUnverifiedException
     */
    private function convertWithProvider(
        User $guest,
        string $provider,
        string $providerId,
        string $email,
        bool $emailVerified,
        ?string $name,
    ): User {
        $this->assertGuestUser($guest);
        $this->assertNotBanned($guest);

        $column = "{$provider}_id";

        // A previously-linked provider id is always safe to match on, regardless of
        // whether the provider verified the email this time — the account already
        // proved ownership by linking. Matching by bare email is only safe when the
        // provider verifies it; an unverified email is just an unproven claim, and
        // auto-linking/merging into an existing account on that basis would let
        // anyone claiming a victim's email take over their account.
        $existing = User::where('type', UserType::App)
            ->where(function ($q) use ($column, $providerId, $email, $emailVerified) {
                $q->where($column, $providerId);

                if ($emailVerified) {
                    $q->orWhere(fn ($sub) => $sub->where('email', $email)->whereNull($column));
                }
            })
            ->first();

        if ($existing) {
            return $this->merger->mergeFromProvider($guest, $existing, $provider, $providerId);
        }

        // Not matched above, but an app account already owns this exact email (under a
        // different, or no, provider link) — creating a new row would also collide with
        // users.email's own DB-level unique constraint. Refuse rather than silently
        // merging on an unverified claim.
        if (! $emailVerified && User::where('type', UserType::App)->where('email', $email)->exists()) {
            throw new ProviderEmailUnverifiedException(
                'This provider did not verify the email address, and it is already associated with an existing account.'
            );
        }

        $guest->forceFill([
            'type' => UserType::App,
            'email' => $email,
            $column => $providerId,
            'name' => $name ?: $guest->name,
            'password' => Hash::make(Str::random(64)),
            'email_verified_at' => $emailVerified ? now() : null,
        ])->save();

        ActivityLogger::log(ActivityModule::Guest, ActivityAction::Converted, $guest, [
            'initiated_by' => 'self',
            'provider' => $provider,
        ]);

        return $guest->fresh();
    }

    private function convert(User $guest, string $email, ?string $name, string $hashedPassword, string $initiatedBy, bool $markEmailVerified = false): void
    {
        $oldEmail = $guest->email;

        $guest->forceFill([
            'type' => UserType::App,
            'email' => $email,
            'name' => $name ?: $guest->name,
            'password' => $hashedPassword,
            'email_verified_at' => $markEmailVerified ? now() : null,
        ])->save();

        ActivityLogger::log(ActivityModule::Guest, ActivityAction::Converted, $guest, [
            'initiated_by' => $initiatedBy,
            'old_email' => $oldEmail,
            'new_email' => $email,
            'email_verified_by_admin' => $markEmailVerified,
        ]);

        if (! $markEmailVerified) {
            $guest->sendEmailVerificationNotification();
        }
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
