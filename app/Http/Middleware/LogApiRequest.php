<?php

namespace App\Http\Middleware;

use App\Support\ApiLogs\ApiLogBuffer;
use App\Support\ApiLogs\RecordBuilder;
use App\Support\ApiLogs\RequestRecorder;
use App\Support\ApiRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Global (prepended right after AssignRequestIds). handle() only starts a
 * clock; all real work — building, sampling, sanitizing and buffering the
 * record — happens in terminate(), which PHP-FPM runs after the response has
 * already been flushed to the client. Every step is guarded: a logging
 * failure is reported (rate-limited) and dropped, never surfaced to a request.
 */
class LogApiRequest
{
    public function __construct(private readonly RequestRecorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldLog($request)) {
            $this->recorder->start($request);
        } else {
            $this->recorder->reset();
        }

        $response = $next($request);

        $this->recorder->mark('response_ready');

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $recorder = app(RequestRecorder::class);

        if (! $recorder->isActive()) {
            return;
        }

        try {
            $record = app(RecordBuilder::class)->build($request, $response, $recorder);

            if ($record !== null) {
                app(ApiLogBuffer::class)->push($record);
            }
        } catch (Throwable $e) {
            if (RateLimiter::attempt('api-logs:write-failure', 1, fn (): bool => true, 60)) {
                Log::warning('API request log dropped', ['exception' => $e]);
            }
        } finally {
            $recorder->reset();
        }
    }

    private function shouldLog(Request $request): bool
    {
        if (! config('api_logs.enabled', true) || ! ApiRequest::targets($request)) {
            return false;
        }

        $excluded = (array) config('api_logs.exclude_paths', []);

        return $excluded === [] || ! Str::is($excluded, $request->path());
    }
}
