<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\DeviceType;
use App\Enum\UserType;
use App\Exceptions\DeviceBlockedException;
use App\Exceptions\DeviceLimitExceededException;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\V1\Auth\LoginRequest;
use App\Http\Requests\V1\Auth\SignupRequest;
use App\Http\Resources\V1\UserDeviceResource;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\Device\DeviceService;
use App\Support\ActivityLogger;
use App\Support\DeviceData;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends ApiController
{
    /** Per email+IP, mirrors the panel's own Livewire\Auth\Login::ensureIsNotRateLimited(). */
    private const MAX_LOGIN_ATTEMPTS = 5;

    public function signup(SignupRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'type' => UserType::App,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        // Explicit causer: the account, not auth()->user() — no session
        // exists yet at signup, but the registration was still self-initiated,
        // same as GuestConversionService::convertBySelf() attributes its
        // 'self' path.
        ActivityLogger::log(ActivityModule::User, ActivityAction::Created, $user, [
            'attributes' => ['name' => $user->name, 'email' => $user->email, 'type' => $user->type->value],
            'initiated_by' => 'self',
        ], causer: $user);

        $user->sendEmailVerificationNotification();

        return $this->created([
            'user' => new UserResource($user),
        ], 'Account created successfully.');
    }

    /**
     * Deliberately looks up `->withTrashed()` and checks `type` in the same
     * query as the password check: a trashed/staff/guest email must be
     * indistinguishable from a wrong password in the response — anything
     * else leaks account existence/type to an unauthenticated caller.
     */
    public function login(LoginRequest $request, DeviceService $devices): JsonResponse
    {
        $validated = $request->validated();
        $email = $validated['email'];
        $throttleKey = $this->throttleKey($email, $request);

        if ($limited = $this->rateLimitResponse($throttleKey, $request)) {
            return $limited;
        }

        $user = User::withTrashed()
            ->where('email', $email)
            ->where('type', UserType::App)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey);
            event(new Failed('sanctum', $user, ['email' => $email]));

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        // Password verified — clear the limiter now. Everything past this
        // point is an account-state/authorization check, not a
        // credential-guessing signal, so it shouldn't count against it.
        RateLimiter::clear($throttleKey);

        if ($user->trashed()) {
            return $this->error('This account no longer exists.', Response::HTTP_GONE);
        }

        if ($user->isBanned()) {
            return $this->error('This account has been banned.', Response::HTTP_FORBIDDEN, [
                'code' => 'ACCOUNT_BANNED',
                'reason' => $user->ban_reason,
            ]);
        }

        $token = $user->createToken($validated['device']['name'] ?? 'device');

        try {
            $device = $devices->register(
                $user,
                $this->deviceDataFrom($validated['device']),
                $token->accessToken,
                $request->ip(),
            );
        } catch (DeviceBlockedException $e) {
            $token->accessToken->delete();

            return $this->error($e->getMessage(), Response::HTTP_FORBIDDEN, ['code' => 'DEVICE_BLOCKED']);
        } catch (DeviceLimitExceededException $e) {
            $token->accessToken->delete();

            return $this->error($e->getMessage(), Response::HTTP_FORBIDDEN, ['code' => 'DEVICE_LIMIT_EXCEEDED']);
        }

        // Also writes User::last_login — see AuthActivityListener::handleLogin().
        event(new Login('sanctum', $user, false));

        return $this->success([
            'user' => new UserResource($user),
            'device' => new UserDeviceResource($device),
            'token' => $token->plainTextToken,
            'pending_deletion' => $user->isPendingDeletion() ? [
                'purges_at' => $user->deletionPurgesAt()?->toIso8601String(),
                'can_cancel' => $user->canCancelDeletion(),
            ] : null,
        ], 'Login successful.');
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function deviceDataFrom(array $device): DeviceData
    {
        return new DeviceData(
            fingerprint: $device['fingerprint'],
            name: $device['name'] ?? null,
            model: $device['model'] ?? null,
            platform: $device['platform'] ?? null,
            os: $device['os'] ?? null,
            deviceType: isset($device['type']) ? DeviceType::from($device['type']) : null,
            appVersion: $device['app_version'] ?? null,
        );
    }

    private function rateLimitResponse(string $key, Request $request): ?JsonResponse
    {
        if (! RateLimiter::tooManyAttempts($key, self::MAX_LOGIN_ATTEMPTS)) {
            return null;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($key);

        return $this->error(
            "Too many login attempts. Please try again in {$seconds} seconds.",
            Response::HTTP_TOO_MANY_REQUESTS,
        );
    }

    /** Prefixed distinctly from the panel's own (unprefixed) key so the two surfaces never collide. */
    private function throttleKey(string $email, Request $request): string
    {
        return 'api-login:'.Str::transliterate(Str::lower($email)).'|'.$request->ip();
    }
}
