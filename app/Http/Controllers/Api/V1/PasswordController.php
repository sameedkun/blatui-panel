<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\UserType;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\V1\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordController extends ApiController
{
    private const GENERIC_ERROR = 'Unable to reset password. The link may be invalid or expired.';

    /**
     * Send a password reset link.
     *
     * Always responds the same way regardless of the broker's actual status
     * (no such user, already-throttled, ...) — never leaks account existence.
     * The `type` credential scopes the lookup to app users at the query
     * level (EloquentUserProvider::retrieveByCredentials() where()s every
     * non-password credential key), so staff/guest accounts can never get a
     * reset link through this guest endpoint.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink([
            'email' => $request->validated('email'),
            'type' => UserType::App->value,
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            $user = User::where('email', $request->validated('email'))
                ->where('type', UserType::App)
                ->first();

            if ($user) {
                // Mirrors Users/Show::sendPasswordResetLink()'s admin-triggered
                // shape, causer the user themselves — self-initiated, no session.
                ActivityLogger::log(ActivityModule::User, ActivityAction::Sent, $user, [
                    'type' => 'password_reset',
                ], causer: $user);
            }
        }

        return $this->success(null, 'If that email exists, a password reset link has been sent.');
    }

    /**
     * Reset the password using a broker token.
     *
     * `email` arrives Crypt::encryptString()'d — see UrlResolver::passwordResetUrl()'s
     * docblock: "any future API endpoint must do the same" as the panel's own
     * PasswordReset Livewire component, which decrypts it in mount().
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $email = Crypt::decryptString(urldecode($request->validated('email')));
        } catch (DecryptException) {
            return $this->error(self::GENERIC_ERROR, 422);
        }

        // Mirrors Livewire\Auth\PasswordReset::resetPassword()'s broker call
        // and closure exactly, so a reset via the API is indistinguishable
        // in the audit trail from one via the panel page.
        $status = Password::reset(
            [
                'email' => $email,
                'type' => UserType::App->value,
                'password' => $request->validated('password'),
                'token' => $request->validated('token'),
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // AuthActivityListener::handlePasswordReset() logs this for free.
                event(new PasswordResetEvent($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error(self::GENERIC_ERROR, 422);
        }

        return $this->success(null, 'Password reset successfully.');
    }
}
