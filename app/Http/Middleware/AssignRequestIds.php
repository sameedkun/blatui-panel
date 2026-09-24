<?php

namespace App\Http\Middleware;

use App\Support\ApiLogs\RequestIds;
use App\Support\ApiRequest;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global (prepended first) rather than in the `api` group, so requests that
 * never reach that group still get ids: typo'd endpoints matching no route,
 * CheckBlockedIp rejections, and laravel-apiroute's version-negotiation
 * errors. A no-op for anything outside the API surface.
 *
 * Both ids are echoed as response headers. Every error envelope
 * (`{"status": false, ...}` — ApiController::error(), thrown exceptions via
 * ApiExceptionRenderer, and the hand-built ones in CheckBlockedIp /
 * EnsureDeviceIsValid alike) additionally gets a top-level `request_id`, so an
 * app developer can copy it straight out of the error they're looking at.
 */
class AssignRequestIds
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ApiRequest::targets($request)) {
            // Never let a previous API request's ids (several requests sharing
            // one application, as in the test suite) leak onto this one.
            Context::forget([RequestIds::REQUEST_ID_KEY, RequestIds::CORRELATION_ID_KEY]);

            return $next($request);
        }

        $requestId = RequestIds::generateRequestId();
        $correlationId = RequestIds::resolveCorrelationId($request->header(RequestIds::CORRELATION_ID_HEADER));

        Context::add([
            RequestIds::REQUEST_ID_KEY => $requestId,
            RequestIds::CORRELATION_ID_KEY => $correlationId,
        ]);

        $response = $next($request);

        $response->headers->set(RequestIds::REQUEST_ID_HEADER, $requestId);
        $response->headers->set(RequestIds::CORRELATION_ID_HEADER, $correlationId);

        $this->stampErrorEnvelope($response, $requestId);

        return $response;
    }

    private function stampErrorEnvelope(Response $response, string $requestId): void
    {
        if (! $response instanceof JsonResponse || $response->getStatusCode() < 400) {
            return;
        }

        $data = $response->getData(true);

        if (! is_array($data) || ($data['status'] ?? null) !== false || array_key_exists('request_id', $data)) {
            return;
        }

        $response->setData([...$data, 'request_id' => $requestId]);
    }
}
