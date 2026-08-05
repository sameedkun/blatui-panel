<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

use App\Http\Controllers\Api\ApiController;
use App\Support\ApiRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Reformats framework-thrown exceptions into ApiController's response
 * envelope for requests targeting the API surface (see ApiRequest), so a
 * 422 from a FormRequest looks identical to one built by hand via
 * ApiController::validationError(). Registered from bootstrap/app.php's
 * withExceptions(). Each method returns null for non-API requests so
 * Laravel's default HTML/redirect rendering still applies there.
 *
 * AuthorizationException and ModelNotFoundException aren't handled
 * explicitly: Laravel's Handler::prepareException() converts both into an
 * HttpExceptionInterface implementation (AccessDeniedHttpException /
 * NotFoundHttpException) before render callbacks ever run, so http()
 * already covers them.
 */
final class ApiExceptionRenderer
{
    public static function validation(ValidationException $e, Request $request): ?JsonResponse
    {
        if (! ApiRequest::targets($request)) {
            return null;
        }

        return ApiController::error('Validation failed', $e->status, $e->errors());
    }

    public static function unauthenticated(AuthenticationException $e, Request $request): ?JsonResponse
    {
        if (! ApiRequest::targets($request)) {
            return null;
        }

        return ApiController::error('Unauthenticated', Response::HTTP_UNAUTHORIZED);
    }

    public static function http(HttpExceptionInterface $e, Request $request): ?JsonResponse
    {
        if (! ApiRequest::targets($request)) {
            return null;
        }

        return ApiController::error(
            $e->getMessage() !== '' ? $e->getMessage() : 'Error',
            $e->getStatusCode(),
        );
    }

    public static function fallback(Throwable $e, Request $request): ?JsonResponse
    {
        if (! ApiRequest::targets($request)) {
            return null;
        }

        return ApiController::error(
            config('app.debug') ? $e->getMessage() : 'Server error',
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }
}
