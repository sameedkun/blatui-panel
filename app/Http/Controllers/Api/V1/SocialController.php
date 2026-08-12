<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\UserType;
use App\Exceptions\DeviceBlockedException;
use App\Exceptions\DeviceLimitExceededException;
use App\Exceptions\ProviderTokenInvalidException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\V1\Concerns\ResolvesSocialiteUser;
use App\Http\Requests\V1\Auth\SocialLoginRequest;
use App\Http\Resources\V1\UserDeviceResource;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\Device\BrowserDeviceResolver;
use App\Services\Device\DeviceService;
use App\Support\ActivityLogger;
use App\Support\DeviceData;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SocialController extends ApiController
{
    use ResolvesSocialiteUser;

    /**
     * Sign in with Google/Apple.
     *
     * the same login/signup entry point as
     * AuthController::signup()/login(), just authenticated by a Socialite-
     * verified provider token instead of a password. An unrecognized token
     * creates a brand-new app account (signup); a recognized one logs in
     * (login) — the caller doesn't pick which, the account state decides.
     * Every device/ban/trashed check AuthController::login() performs applies
     * here identically, since both ultimately hand a session-worthy account to
     * the same device-registration + token-issuance pipeline.
     */
    public function login(SocialLoginRequest $request, string $provider, DeviceService $devices, BrowserDeviceResolver $browserDevices): JsonResponse
    {
        try {
            $socialiteUser = $this->resolveSocialiteUser($provider, $request->validated('token'));
        } catch (ProviderTokenInvalidException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, ['code' => 'PROVIDER_TOKEN_INVALID']);
        }

        $email = $socialiteUser->getEmail();

        if (! $email) {
            return $this->error(
                'This provider did not share an email address.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['code' => 'PROVIDER_EMAIL_MISSING'],
            );
        }

        $column = "{$provider}_id";
        $providerId = (string) $socialiteUser->getId();
        $emailVerified = $this->isSocialiteEmailVerified($socialiteUser);

        // Same account-linking logic as GuestConversionService::convertWithProvider():
        // match by provider id first, otherwise by email on an account that
        // has never linked this provider — an OAuth-verified email is proof
        // of ownership, so auto-linking here is safe.
        $user = User::withTrashed()
            ->where('type', UserType::App)
            ->where(fn ($q) => $q->where($column, $providerId)
                ->orWhere(fn ($sub) => $sub->where('email', $email)->whereNull($column)))
            ->first();

        $isNewUser = $user === null;

        if ($isNewUser) {
            $user = User::create([
                'type' => UserType::App,
                'name' => $socialiteUser->getName() ?: $request->validated('name') ?: 'User',
                'email' => $email,
                $column => $providerId,
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => $emailVerified ? now() : null,
                'registration_date' => now(),
            ]);

            ActivityLogger::log(ActivityModule::User, ActivityAction::Created, $user, [
                'attributes' => ['name' => $user->name, 'email' => $user->email, 'type' => $user->type->value],
                'initiated_by' => 'self',
                'provider' => $provider,
            ], causer: $user);

            if (! $emailVerified) {
                $user->sendEmailVerificationNotification();
            }
        }

        if ($user->trashed()) {
            return $this->error(
                'This account has been deleted. If you believe this is a mistake, please contact support.',
                Response::HTTP_GONE
            );
        }

        if ($user->isPendingDeletion() && $user->deletionPurgesAt()->isPast()) {
            return $this->error(
                'This account is currently being deleted. Please try again later or contact support.',
                Response::HTTP_GONE
            );
        }

        if ($user->isBanned()) {
            return $this->error('This account has been banned.', Response::HTTP_FORBIDDEN, [
                'code' => 'ACCOUNT_BANNED',
                'reason' => $user->ban_reason,
            ]);
        }

        if (! $isNewUser && $user->{$column} !== $providerId) {
            $user->forceFill([$column => $providerId])->save();
        }

        $isBrowser = $request->isBrowserClient();

        $deviceData = $isBrowser
            ? $browserDevices->resolve($request)
            : DeviceData::fromRequestArray($request->validated('device'));

        $token = $user->createToken($deviceData->name ?? 'device');

        try {
            $device = $devices->register($user, $deviceData, $token->accessToken, $request->ip());
        } catch (DeviceBlockedException $e) {
            $token->accessToken->delete();

            return $this->error($e->getMessage(), Response::HTTP_FORBIDDEN, ['code' => 'DEVICE_BLOCKED']);
        } catch (DeviceLimitExceededException $e) {
            $token->accessToken->delete();

            return $this->error($e->getMessage(), Response::HTTP_FORBIDDEN, ['code' => 'DEVICE_LIMIT_EXCEEDED']);
        }

        // Also writes User::last_login — see AuthActivityListener::handleLogin().
        event(new Login('sanctum', $user, false));

        $response = $this->success([
            'user' => new UserResource($user),
            'device' => new UserDeviceResource($device),
            'token' => $token->plainTextToken,
            'is_new_user' => $isNewUser,
        ], $isNewUser ? 'Account created successfully.' : 'Login successful.');

        if ($isBrowser) {
            $response->cookie(cookie(
                name: BrowserDeviceResolver::COOKIE_NAME,
                value: $deviceData->fingerprint,
                minutes: 60 * 24 * 365,
                path: '/',
                secure: ! app()->isLocal(),
                httpOnly: true,
                sameSite: 'lax',
            ));
        }

        return $response;
    }
}
