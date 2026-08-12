<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Exceptions\ProviderTokenInvalidException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Shared by every controller that verifies a provider token via Socialite
 * (GuestController::convertWithProvider(), SocialController::login()) —
 * keeps the "how do we call Socialite" and "how do we read email_verified"
 * logic in one place rather than duplicated per call site.
 */
trait ResolvesSocialiteUser
{
    /** @throws ProviderTokenInvalidException */
    protected function resolveSocialiteUser(string $provider, string $token): SocialiteUser
    {
        try {
            return $provider === 'apple'
                ? Socialite::driver('apple')->stateless()->userFromToken($token)
                : Socialite::driver('google')->userFromToken($token);
        } catch (Throwable $e) {
            throw new ProviderTokenInvalidException('Unable to verify the provided token.', previous: $e);
        }
    }

    /**
     * Google's userinfo response and Apple's id_token both carry an
     * `email_verified` claim — Apple sometimes sends it as the string
     * "true"/"false" rather than a real boolean, so this normalizes either
     * shape. Missing entirely defaults to verified: both providers only
     * hand back an email at all once it's confirmed on their side.
     */
    protected function isSocialiteEmailVerified(SocialiteUser $user): bool
    {
        return filter_var($user->getRaw()['email_verified'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }
}
