<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\UserType;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\V1\Guest\ConvertGuestProviderRequest;
use App\Http\Requests\V1\Guest\ConvertGuestRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\Account\GuestConversionService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Anonymous guest accounts — a zero-friction identity a client can create on
 * first launch, then later turn into a real app account via convert(). No
 * password is ever returned: the column is NOT NULL so one still has to be
 * set, but nothing accepts guest credentials for login (AuthController::
 * login() is hard-scoped to UserType::App), so exposing it would just be a
 * credential that can never actually be used anywhere.
 */
class GuestController extends ApiController
{
    public function store(): JsonResponse
    {
        $guest = User::create([
            'type' => UserType::Guest,
            'name' => 'Guest',
            'email' => $this->generateGuestEmail(),
            'password' => Hash::make(Str::random(64)),
            'registration_date' => now(),
        ]);

        ActivityLogger::log(ActivityModule::Guest, ActivityAction::Created, $guest, [
            'initiated_by' => 'self',
        ], causer: $guest);

        $token = $guest->createToken('guest');

        return $this->created([
            'user' => new UserResource($guest),
            'token' => $token->plainTextToken,
        ], 'Guest account created.');
    }

    /**
     * Email + password self-service conversion — never merges (see
     * ConvertGuestRequest's docblock). The guest row is mutated in place
     * (same id), so the caller's existing bearer token keeps working; no new
     * token is issued or returned.
     */
    public function convert(ConvertGuestRequest $request, GuestConversionService $conversions): JsonResponse
    {
        /** @var User $guest */
        $guest = $request->user();

        $converted = $conversions->convertBySelf(
            $guest,
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('name'),
        );

        return $this->success(['user' => new UserResource($converted)], 'Account converted. Please verify your email.');
    }

    /**
     * Provider-verified conversion — google gets an OAuth access token,
     * apple gets an identity (id_token) JWT; both are handed to Socialite's
     * userFromToken(), which calls the provider directly to resolve the
     * real id/email/name. Nothing about identity is ever trusted from the
     * client. May transparently merge into an existing app account instead
     * of converting (see GuestConversionService::convertWithGoogle()/
     * convertWithApple()). A merge force-deletes the guest row, orphaning
     * the token that authenticated this very request (personal_access_tokens
     * has no real FK to clean it up), so this issues and returns a fresh
     * token for the destination account in that case. A plain convert keeps
     * the same row and the caller's existing token, so no new token is
     * returned.
     */
    public function convertWithProvider(
        ConvertGuestProviderRequest $request,
        string $provider,
        GuestConversionService $conversions,
    ): JsonResponse {
        /** @var User $guest */
        $guest = $request->user();
        $guestId = $guest->id;

        try {
            $socialiteUser = $provider === 'apple'
                ? Socialite::driver('apple')->stateless()->userFromToken($request->validated('token'))
                : Socialite::driver('google')->userFromToken($request->validated('token'));
        } catch (Throwable) {
            return $this->error(
                'This sign-in token could not be verified. Please try again.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['code' => 'PROVIDER_TOKEN_INVALID'],
            );
        }

        $email = $socialiteUser->getEmail();

        if (! $email) {
            return $this->error(
                'This provider did not share an email address.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['code' => 'PROVIDER_EMAIL_MISSING'],
            );
        }

        $name = $socialiteUser->getName() ?: $request->validated('name');
        $providerId = (string) $socialiteUser->getId();

        $result = $provider === 'google'
            ? $conversions->convertWithGoogle($guest, $providerId, $email, $this->isEmailVerified($socialiteUser), $name)
            : $conversions->convertWithApple($guest, $providerId, $email, $this->isEmailVerified($socialiteUser), $name);

        $merged = $result->id !== $guestId;
        $data = ['user' => new UserResource($result)];

        if ($merged) {
            $guest->currentAccessToken()->delete();

            $data['token'] = $result->createToken('guest-merged')->plainTextToken;
        }

        return $this->success($data, $merged ? 'Account merged with an existing account.' : 'Account converted.');
    }

    /**
     * Google's userinfo response and Apple's id_token both carry an
     * `email_verified` claim — Apple sometimes sends it as the string
     * "true"/"false" rather than a real boolean, so this normalizes either
     * shape. Missing entirely defaults to verified: both providers only
     * hand back an email at all once it's confirmed on their side.
     */
    private function isEmailVerified(SocialiteUser $user): bool
    {
        return filter_var($user->getRaw()['email_verified'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    private function generateGuestEmail(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'example.com';

        do {
            $email = 'guest_'.Str::lower(Str::random(12))."@{$host}";
        } while (User::where('email', $email)->exists());

        return $email;
    }
}
