<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSuperAdmin();
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

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

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
