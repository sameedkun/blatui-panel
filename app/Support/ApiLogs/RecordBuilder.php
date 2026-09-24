<?php

declare(strict_types=1);

namespace App\Support\ApiLogs;

use App\Models\ApiLog\ApiRequestLog;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Turns a finished request (plus what {@see RequestRecorder} collected while
 * it ran) into the rows the API log stores — already sampled and sanitized.
 * Runs in terminate(), after the response has been sent, so none of this
 * work is on the client's clock.
 *
 * @phpstan-type ApiLogRecord array{
 *     log: array<string, mixed>,
 *     payload: array<string, mixed>|null,
 *     exceptions: list<array<string, mixed>>,
 * }
 */
class RecordBuilder
{
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s.v';

    /** Same header LoginRequest/SocialLoginRequest branch on (DetectsBrowserClient). */
    private const string CLIENT_TYPE_HEADER = 'X-Client-Type';

    public function __construct(
        private readonly Sanitizer $sanitizer,
        private readonly SamplingPolicy $sampling,
    ) {}

    /**
     * The record to store, or null when sampling drops the request.
     *
     * @return ApiLogRecord|null
     */
    public function build(Request $request, Response $response, RequestRecorder $recorder): ?array
    {
        $requestId = RequestIds::requestId() ?? RequestIds::generateRequestId();
        $correlationId = RequestIds::correlationId() ?? RequestIds::resolveCorrelationId(null);
        $statusCode = $response->getStatusCode();
        $statusClass = intdiv($statusCode, 100);
        $durationMs = $recorder->durationMs();
        $responseData = $this->decodeJsonResponse($response);
        $errorCode = $this->errorCode($responseData);
        $exceptions = $recorder->exceptions();

        $weight = $this->sampling->weight(
            $statusClass,
            forceKeep: $exceptions !== [] || $errorCode !== null || $durationMs >= (int) config('api_logs.slow_request_ms', 1000),
        );

        if ($weight === null) {
            return null;
        }

        $route = $request->route();
        $routeUri = $route?->uri() ?? ApiRequestLog::UNMATCHED_ROUTE;
        $createdAt = now()->format(self::DATETIME_FORMAT);
        [$user, $tokenId] = $this->resolveUser();
        $device = $request->attributes->get('user_device');
        $payload = $this->payload($request, $response, $responseData, $recorder, $routeUri, $statusCode);

        $log = [
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'method' => $request->getMethod(),
            'path' => Str::limit($this->sanitizePath($request), 2048, ''),
            'route_uri' => $routeUri,
            'route_name' => $route?->getName(),
            'api_version' => $this->apiVersion($request),
            'status_code' => $statusCode,
            'status_class' => $statusClass,
            'duration_ms' => $durationMs,
            'memory_peak_kb' => intdiv(memory_get_peak_usage(true), 1024),
            'db_query_count' => min($recorder->queryCount(), 65535),
            'db_time_ms' => $recorder->queryTimeMs(),
            'request_size' => (int) $request->headers->get('Content-Length', '0'),
            'response_size' => $this->responseSize($response),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, '') ?: null,
            'client_type' => $request->header(self::CLIENT_TYPE_HEADER) === 'web' ? 'web' : 'app',
            'user_id' => $user?->getAuthIdentifier(),
            'user_type' => $user instanceof User ? $user->type?->value : null,
            'token_id' => $tokenId,
            'device_id' => $device instanceof UserDevice ? $device->id : null,
            'error_code' => $errorCode,
            'has_payload' => $payload !== null,
            'has_exception' => $exceptions !== [],
            'sample_weight' => $weight,
            'timings' => $recorder->marks(),
            'created_at' => $createdAt,
        ];

        return [
            'log' => $log,
            'payload' => $payload === null ? null : [...$payload, 'request_id' => $requestId, 'created_at' => $createdAt],
            'exceptions' => array_map(fn (Throwable $exception): array => [
                ...$this->exception($exception),
                'request_id' => $requestId,
                'correlation_id' => $correlationId,
                'method' => $log['method'],
                'route_uri' => $routeUri,
                'status_code' => $statusCode,
                'user_id' => $log['user_id'],
                'created_at' => $createdAt,
            ], $exceptions),
        ];
    }

    /**
     * Headers + bodies, or null when there's nothing worth keeping for this
     * request (see config('api_logs.capture')).
     *
     * @return array<string, mixed>|null
     */
    private function payload(Request $request, Response $response, mixed $responseData, RequestRecorder $recorder, string $routeUri, int $statusCode): ?array
    {
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        $files = $this->describeFiles($request->allFiles());
        $requestBody = $files === [] ? $body : array_replace_recursive($body, $files);
        $query = $request->query->all();

        $worthKeeping = $requestBody !== []
            || $query !== []
            || $statusCode >= 400
            || $recorder->slowQueries() !== []
            || ! in_array($request->getMethod(), ['GET', 'HEAD'], true)
            || config('api_logs.capture.successful_reads')
            || Str::is((array) config('api_logs.capture.always', []), $routeUri);

        if (! $worthKeeping) {
            return null;
        }

        [$storedRequestBody, $requestTruncated] = $this->limit($requestBody === [] ? null : $this->sanitizer->data($requestBody));
        [$storedResponseBody, $responseTruncated] = $this->limit($this->responseBody($response, $responseData));

        return [
            'request_headers' => $this->sanitizer->headers($request->headers->all()),
            'query' => $query === [] ? null : $this->sanitizer->data($query),
            'request_body' => $storedRequestBody,
            'response_headers' => $this->sanitizer->headers($response->headers->all()),
            'response_body' => $storedResponseBody,
            'slow_queries' => $recorder->slowQueries() ?: null,
            'request_truncated' => $requestTruncated,
            'response_truncated' => $responseTruncated,
        ];
    }

    private function responseBody(Response $response, mixed $responseData): mixed
    {
        if ($responseData !== null) {
            return $this->sanitizer->data($responseData);
        }

        $size = $this->responseSize($response);

        return $size ? ['content_type' => $response->headers->get('Content-Type'), 'size' => $size] : null;
    }

    /**
     * A body over config('api_logs.max_body_kb') becomes a truncated JSON
     * string — a cut-off document is no longer valid JSON to store decoded.
     *
     * @return array{0: mixed, 1: bool}
     */
    private function limit(mixed $data): array
    {
        if ($data === null) {
            return [null, false];
        }

        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
        $max = max(1, (int) config('api_logs.max_body_kb', 32)) * 1024;

        return strlen($encoded) > $max ? [mb_strcut($encoded, 0, $max), true] : [$data, false];
    }

    private function decodeJsonResponse(Response $response): mixed
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return null;
        }

        if (! str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            return null;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return null;
        }

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The machine-readable error code of a failed response: `errors.code` from
     * ApiController's envelope, or the top-level `error` the hand-built
     * middleware responses (IP_BLOCKED, DEVICE_REVOKED, ...) use.
     */
    private function errorCode(mixed $responseData): ?string
    {
        if (! is_array($responseData)) {
            return null;
        }

        $code = $responseData['errors']['code'] ?? $responseData['error'] ?? null;

        return is_string($code) && preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $code) === 1 ? $code : null;
    }

    /**
     * Whoever authenticated during the request, read only from guards that
     * already resolved a user — never triggers a fresh token lookup.
     *
     * @return array{0: Authenticatable|null, 1: int|null}
     */
    private function resolveUser(): array
    {
        foreach (array_unique(['sanctum', (string) config('auth.defaults.guard')]) as $guardName) {
            $guard = Auth::guard($guardName);

            if (method_exists($guard, 'hasUser') && $guard->hasUser()) {
                $user = $guard->user();
                $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

                return [$user, $token instanceof PersonalAccessToken ? $token->getKey() : null];
            }
        }

        return [null, null];
    }

    /**
     * Route parameters named like a secret (the email-verification link's
     * {hash}, say) are redacted out of the stored path too.
     */
    private function sanitizePath(Request $request): string
    {
        $path = $request->getPathInfo();

        foreach ($request->route()?->originalParameters() ?? [] as $name => $value) {
            if (is_string($value) && $value !== '' && $this->sanitizer->isSecretKey((string) $name)) {
                $path = str_replace('/'.$value, '/'.Sanitizer::REDACTED, $path);
            }
        }

        return $path;
    }

    private function apiVersion(Request $request): ?string
    {
        $version = $request->attributes->get('api_version');

        return is_scalar($version) ? (string) $version : null;
    }

    private function responseSize(Response $response): ?int
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            $length = $response->headers->get('Content-Length');

            return is_numeric($length) ? (int) $length : null;
        }

        $content = $response->getContent();

        return is_string($content) ? strlen($content) : null;
    }

    /**
     * Uploaded files are described, never stored.
     *
     * @param  array<string, mixed>  $files
     * @return array<string, mixed>
     */
    private function describeFiles(array $files): array
    {
        return array_map(fn (mixed $file): mixed => $file instanceof UploadedFile
            ? ['file' => $file->getClientOriginalName(), 'size' => $file->getSize(), 'mime' => $file->getClientMimeType()]
            : (is_array($file) ? $this->describeFiles($file) : null), $files);
    }

    /**
     * @return array<string, mixed>
     */
    private function exception(Throwable $exception): array
    {
        $file = $this->relativePath($exception->getFile());

        $previous = [];
        $cause = $exception->getPrevious();

        while ($cause !== null && count($previous) < 5) {
            $previous[] = [
                'class' => $cause::class,
                'message' => $this->sanitizer->text(Str::limit($cause->getMessage(), 1000)),
                'file' => $this->relativePath($cause->getFile()),
                'line' => $cause->getLine(),
            ];
            $cause = $cause->getPrevious();
        }

        return [
            'class' => Str::limit($exception::class, 255, ''),
            'message' => $this->sanitizer->text(Str::limit($exception->getMessage(), 2000)),
            'file' => Str::limit($file, 255, ''),
            'line' => $exception->getLine(),
            'trace' => $this->trace($exception),
            'previous' => $previous ?: null,
            'fingerprint' => sha1($exception::class.'|'.$file.'|'.$exception->getLine()),
        ];
    }

    /**
     * Frame locations only — arguments are never kept, they can hold anything.
     *
     * @return list<array{file: string|null, line: int|null, call: string, app: bool}>
     */
    private function trace(Throwable $exception): array
    {
        $frames = array_slice($exception->getTrace(), 0, (int) config('api_logs.max_trace_frames', 30));

        return array_map(function (array $frame): array {
            $file = isset($frame['file']) ? $this->relativePath($frame['file']) : null;

            return [
                'file' => $file,
                'line' => $frame['line'] ?? null,
                'call' => ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? ''),
                'app' => $file !== null && ! str_starts_with($file, 'vendor/'),
            ];
        }, $frames);
    }

    private function relativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $base = str_replace('\\', '/', base_path()).'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
