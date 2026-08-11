<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enum\UserType;
use App\Http\Controllers\Api\ApiController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Declarative gate for which App\Enum\UserType values may reach a route —
 * e.g. `user.type:app` or `user.type:app,guest`. The reusable mechanism for
 * opting individual API routes/groups into guest access without touching
 * EnsureDeviceIsValid, which stays a purely app-user concept (guests never
 * register a device). Applied *before* device.valid in the app-only route
 * group, so a guest is rejected here first and device.valid never has to
 * know about guests at all.
 */
class EnsureUserType
{
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $allowed = array_map(fn (string $type): UserType => UserType::from($type), $types);

        if (! in_array($request->user()?->type, $allowed, true)) {
            return ApiController::forbidden('This action is not available for your account type.');
        }

        return $next($request);
    }
}
