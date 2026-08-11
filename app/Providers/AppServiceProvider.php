<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Opcodes\LogViewer\Facades\LogViewer;
use SocialiteProviders\Apple\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Replaced by the versioned '/docs/v1/api' routes registered in
        // configureApiDocs() — must be called from register(), before
        // ScrambleServiceProvider::boot() checks the flag.
        Scramble::ignoreDefaultRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSuperAdmin();
        $this->configureLogViewer();
        $this->configureApiDocs();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureSuperAdmin(): void
    {
        Gate::before(function ($user, $ability) {
            // 1. Super admin passes everything
            if ($user->hasRole(config('panel.super_admin_role'))) {
                return true;
            }

            // 2. Module View Inheritance:
            // If checking '{module}.{child}.view' (e.g. settings.mail.view)
            // and the user has the master '{module}.view' (e.g. settings.view), return true.
            $parts = explode('.', $ability);
            if (count($parts) === 3 && end($parts) === 'view') {
                $module = $parts[0];
                $userPermissions = $user->getAllPermissions()->pluck('name');
                if ($userPermissions->contains("{$module}.view")) {
                    return true;
                }
            }

            return null;
        });
    }

    /**
     * Gate the opcodesio/log-viewer package's own routes (/logs) behind the
     * same staff/not-banned checks as the admin panel, plus the 'logs.access'
     * permission — super-admin passes via configureSuperAdmin()'s Gate::before.
     */
    protected function configureLogViewer(): void
    {
        LogViewer::auth(function ($request) {
            $user = $request->user();

            return $user
                && $user->isStaff()
                && ! $user->isBanned()
                && $user->can('logs.access');
        });
    }

    /**
     * Gate dedoc/scramble's own routes (/docs/v1/api, /docs/v1/openapi.json)
     * behind the same staff/not-banned checks as the admin panel, plus the
     * 'api_docs.access' permission — super-admin passes via
     * configureSuperAdmin()'s Gate::before. Scramble's RestrictedDocsAccess
     * middleware only consults this gate outside the 'local' environment; in
     * 'local' the docs stay open, per package default.
     *
     * Registered as a named 'v1' API (rather than relying on Scramble's
     * default routes, disabled in register()) so the docs URL matches this
     * app's own versioned API surface (config/apiroute.php) — a future v2
     * gets its own Scramble::registerApi('v2', ...)->expose(...) block here,
     * mirroring how routes/api/v1.php vs a future v2.php already split.
     */
    protected function configureApiDocs(): void
    {
        Gate::define('viewApiDocs', function ($user) {
            return $user->isStaff()
                && ! $user->isBanned()
                && $user->can('api_docs.access');
        });

        Scramble::registerApi('v1')->expose(
            ui: '/docs/v1/api',
            document: '/docs/v1/openapi.json',
        );
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('apple', Provider::class);
        });

        // Disables the default { "data": [...] } wrap so API Resources nest
        // cleanly inside ApiResponse's own {status, message, data} envelope
        // instead of double-nesting under two "data" keys.
        JsonResource::withoutWrapping();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : Password::min(8),
        );
    }
}
