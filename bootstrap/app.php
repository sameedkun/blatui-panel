<?php

use App\Exceptions\Api\ApiExceptionRenderer;
use App\Http\Middleware\CheckBlockedIp;
use App\Http\Middleware\EnsureDeviceIsValid;
use App\Http\Middleware\EnsurePanelAccess;
use App\Http\Middleware\EnsureUserType;
use App\Http\Middleware\SetLocale;
use App\Support\ApiRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,

            'panel' => EnsurePanelAccess::class,
            'device.valid' => EnsureDeviceIsValid::class,
            'user.type' => EnsureUserType::class,
        ]);

        // Runs before every API route's own middleware (e.g. auth:sanctum),
        // since it needs to reject a blocked IP before authentication even runs.
        $middleware->api(prepend: [CheckBlockedIp::class]);

        $middleware->web(append: [SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => ApiRequest::targets($request),
        );

        // Unifies every API error response (thrown or explicit) under
        // ApiController's envelope — see ApiExceptionRenderer's docblock.
        $exceptions->render(fn (ValidationException $e, Request $request) => ApiExceptionRenderer::validation($e, $request));
        $exceptions->render(fn (AuthenticationException $e, Request $request) => ApiExceptionRenderer::unauthenticated($e, $request));
        $exceptions->render(fn (HttpExceptionInterface $e, Request $request) => ApiExceptionRenderer::http($e, $request));
        $exceptions->render(fn (Throwable $e, Request $request) => ApiExceptionRenderer::fallback($e, $request));
    })->create();
