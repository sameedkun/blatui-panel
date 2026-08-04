<?php

namespace App\Services\Account;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\UserType;
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

        $existing = User::where('type', UserType::App)
            ->where(fn ($q) => $q->where($column, $providerId)
                ->orWhere(fn ($sub) => $sub->where('email', $email)->whereNull($column)))
            ->first();

        if ($existing) {
            return $this->merger->mergeFromProvider($guest, $existing, $provider, $providerId);
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
