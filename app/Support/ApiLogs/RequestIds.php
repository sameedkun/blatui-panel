<?php

declare(strict_types=1);

namespace App\Support\ApiLogs;

use App\Http\Middleware\AssignRequestIds;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * The two identifiers every API request carries, assigned by
 * {@see AssignRequestIds}:
 *
 *   - request id     — `req_` + ULID. Always generated server-side, never
 *                      accepted from a client: it must identify exactly one
 *                      request in the logs, so a client can't be allowed to
 *                      reuse or collide one.
 *   - correlation id — groups the requests (and the queued jobs they
 *                      dispatch) that make up one logical operation, e.g.
 *                      signup → verify → login. Accepted from the client's
 *                      X-Correlation-Id when well-formed, else generated
 *                      (`cor_` + ULID).
 *
 * Both live in Laravel's Context, so they're appended to every log line and
 * carried into queued jobs automatically.
 */
final class RequestIds
{
    public const string REQUEST_ID_HEADER = 'X-Request-Id';

    public const string CORRELATION_ID_HEADER = 'X-Correlation-Id';

    public const string REQUEST_ID_KEY = 'request_id';

    public const string CORRELATION_ID_KEY = 'correlation_id';

    private const string CORRELATION_ID_PATTERN = '/^[A-Za-z0-9_-]{8,64}$/';

    public static function generateRequestId(): string
    {
        return 'req_'.Str::ulid();
    }

    /** The client's correlation id when well-formed, otherwise a fresh one. */
    public static function resolveCorrelationId(?string $incoming): string
    {
        if (is_string($incoming) && preg_match(self::CORRELATION_ID_PATTERN, $incoming) === 1) {
            return $incoming;
        }

        return 'cor_'.Str::ulid();
    }

    public static function requestId(): ?string
    {
        $value = Context::get(self::REQUEST_ID_KEY);

        return is_string($value) ? $value : null;
    }

    public static function correlationId(): ?string
    {
        $value = Context::get(self::CORRELATION_ID_KEY);

        return is_string($value) ? $value : null;
    }
}
