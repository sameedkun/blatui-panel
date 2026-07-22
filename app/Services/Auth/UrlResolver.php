<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

use function Illuminate\Log\log;

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
     * Signed email verification URL. Panel and frontend share the same
     * {id}/{hash} signature shape, just under different route names — always
     * a real signed route via URL::temporarySignedRoute(), never a hand-rolled
     * HMAC. Falls back to the panel route (and the panel's own base URL) if
     * the frontend/API one isn't registered yet.
     */
    public function verificationUrl(User $user): string
    {
        $parameters = [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ];

        if ($this->shouldUsePanel($user) || ! Route::has('api.verification.verify')) {
            return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), $parameters);
        }

        // The frontend/API route lives on a different host (e.g. domain.com rather than
        // panel.domain.com) — force the signed URL to be generated against that base.
        return $this->signedRouteOn(config('panel.frontend_url'), 'api.verification.verify', $parameters);
    }

    /**
     * Generate a temporary signed route URL rooted at a specific base URL
     * rather than the app's own APP_URL.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function signedRouteOn(string $baseUrl, string $routeName, array $parameters): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $scheme = str_starts_with($baseUrl, 'https://') ? 'https' : 'http';

        URL::useOrigin($baseUrl);
        URL::forceScheme($scheme);

        try {
            return URL::temporarySignedRoute($routeName, now()->addMinutes(60), $parameters);
        } finally {
            URL::useOrigin(null);
            URL::forceScheme(null);
        }
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
