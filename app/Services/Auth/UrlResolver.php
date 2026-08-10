<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/**
 * Resolves authentication URLs (email verification, password reset) for a user,
 * auto-detecting panel vs frontend from `panel.auth_url_mode`:
 *  - 'panel'    → always the admin panel (routes/auth.php).
 *  - 'frontend' → always the public frontend/API.
 *  - 'auto'     → staff use the panel, everyone else uses the frontend.
 */
class UrlResolver
{
    /**
     * Registered by routes/api/v1.php's guest group as `->name('verification.verify')` —
     * grazulex/laravel-apiroute auto-prefixes every route name in that file with
     * `api.v1.` (config/apiroute.php), so the name actually registered (and checked/
     * generated against here) is this, not the bare `verification.verify` the file
     * itself passes to ->name().
     */
    private const API_VERIFICATION_ROUTE = 'api.v1.verification.verify';

    /**
     * Signed email verification URL. Both the panel and frontend/API branches
     * key on `User::external_id` (a ULID) rather than the raw numeric PK — a
     * link that leaves the server (even staff-only) shouldn't expose a
     * sequential, enumerable id.
     */
    public function verificationUrl(User $user): string
    {
        $hash = sha1($user->getEmailForVerification());

        if ($this->shouldUsePanel($user) || ! Route::has(self::API_VERIFICATION_ROUTE)) {
            return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
                'id' => $user->external_id,
                'hash' => $hash,
            ]);
        }

        return $this->frontendVerificationUrl($user->external_id, $hash);
    }

    /**
     * Laravel's signed-URL check (UrlGenerator::hasCorrectSignature()) always
     * validates against the *receiving* request's own absolute URL — a
     * signature generated against one host can never validate once relayed to
     * a different one. So the signature here is always generated against
     * THIS app's own real API host (never the frontend's, unlike the old
     * behavior) via a plain, un-rerooted temporarySignedRoute() call.
     *
     * The link shown to the user still points at the frontend — a real page
     * for the click to land on rather than a bare JSON response — but it's
     * just a carrier for the API's own already-valid id/hash/expires/
     * signature as query params. The frontend's job is to read those four
     * values off its own URL and relay them, unmodified, to
     * GET {api}/api/v1/email/verify/{id}/{hash}?expires=...&signature=....
     */
    protected function frontendVerificationUrl(string $externalId, string $hash): string
    {
        $apiUrl = URL::temporarySignedRoute(self::API_VERIFICATION_ROUTE, now()->addMinutes(60), [
            'id' => $externalId,
            'hash' => $hash,
        ]);

        parse_str((string) parse_url($apiUrl, PHP_URL_QUERY), $query);

        $baseUrl = rtrim(config('panel.frontend_url'), '/');

        return "{$baseUrl}/email/verify?".http_build_query(['id' => $externalId, 'hash' => $hash] + $query);
    }

    /**
     * Password reset URL. No signed route here — the broker token is already
     * the credential. The email travels encrypted (not plain-text) since it
     * sits in a query string that ends up in logs/browser history.
     */
    public function passwordResetUrl(User $user, string $token): string
    {
        $email = Crypt::encryptString($user->getEmailForPasswordReset());

        if ($this->shouldUsePanel($user)) {
            return route('password.reset', ['token' => $token, 'email' => $email]);
        }

        $baseUrl = rtrim(config('panel.frontend_url'), '/');

        return "{$baseUrl}/reset-password?".http_build_query([
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Whether this user's auth links should point at the panel rather than
     * the public frontend/API.
     */
    protected function shouldUsePanel(User $user): bool
    {
        return match (config('panel.auth_url_mode', 'auto')) {
            'panel' => true,
            'frontend' => false,
            default => $user->isStaff() || ! $this->hasDistinctFrontend(),
        };
    }

    /**
     * Whether a real, separate frontend is actually configured. `frontend_url`
     * silently falls back to `APP_URL` when `FRONTEND_URL` isn't set, so without
     * this check 'auto' mode would send app users to a frontend URL that's
     * really just the panel's own host under a path that doesn't exist there.
     */
    protected function hasDistinctFrontend(): bool
    {
        $frontend = rtrim((string) config('panel.frontend_url'), '/');
        $app = rtrim((string) config('app.url'), '/');

        return $frontend !== '' && $frontend !== $app;
    }
}
