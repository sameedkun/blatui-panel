<?php

declare(strict_types=1);

namespace App\Support\ApiLogs;

use App\Http\Middleware\LogApiRequest;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Throwable;
use WeakReference;

/**
 * Per-request state for the API log — bound as a scoped singleton, so it
 * resets between requests even under Octane. {@see LogApiRequest} activates it
 * for API requests; everything else in the app (the DB listener, the
 * RouteMatched listener, the exception report hook in bootstrap/app.php) calls
 * into it unconditionally and it simply no-ops while inactive.
 *
 * Nothing here does I/O: it only collects timings, query counters and
 * reported exceptions. Building and storing the record happens later, after
 * the response is sent, in {@see RecordBuilder} / {@see ApiLogBuffer}.
 */
class RequestRecorder
{
    /** @var WeakReference<object>|null the event dispatcher the listeners were registered on */
    private static ?WeakReference $listeningOn = null;

    private bool $active = false;

    private float $startedAt = 0.0;

    /** @var array<string, float> phase => ms since the request started */
    private array $marks = [];

    private int $queryCount = 0;

    private float $queryTimeMs = 0.0;

    /** @var list<array{sql: string, time_ms: float}> */
    private array $slowQueries = [];

    /** @var array<int, Throwable> keyed by spl_object_id, so a re-reported exception is kept once */
    private array $exceptions = [];

    public function start(Request $request): void
    {
        $this->reset();
        $this->active = true;

        $requestTime = $request->server('REQUEST_TIME_FLOAT');
        $this->startedAt = is_numeric($requestTime) ? (float) $requestTime : microtime(true);

        $this->marks = ['received' => 0.0];
        $this->mark('middleware_started');

        self::registerListeners();
    }

    /**
     * Back to inactive and empty. The scoped binding already gives each request
     * a fresh instance under FPM/Octane; this covers several requests sharing
     * one application (the test suite).
     */
    public function reset(): void
    {
        $this->active = false;
        $this->startedAt = 0.0;
        $this->marks = [];
        $this->queryCount = 0;
        $this->queryTimeMs = 0.0;
        $this->slowQueries = [];
        $this->exceptions = [];
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function mark(string $phase): void
    {
        if ($this->active && ! isset($this->marks[$phase])) {
            $this->marks[$phase] = round((microtime(true) - $this->startedAt) * 1000, 2);
        }
    }

    public function recordQuery(QueryExecuted $query): void
    {
        if (! $this->active) {
            return;
        }

        $this->queryCount++;
        $this->queryTimeMs += (float) $query->time;

        // Only the SQL text — bindings can carry personal data.
        if ($query->time >= (int) config('api_logs.slow_query_ms', 100)
            && count($this->slowQueries) < (int) config('api_logs.max_slow_queries', 10)) {
            $this->slowQueries[] = ['sql' => $query->sql, 'time_ms' => round((float) $query->time, 2)];
        }
    }

    public function recordException(Throwable $exception): void
    {
        if ($this->active) {
            $this->exceptions[spl_object_id($exception)] = $exception;
        }
    }

    /** Ms from the request's start to the response being ready (or now, if not marked yet). */
    public function durationMs(): float
    {
        return $this->marks['response_ready'] ?? round((microtime(true) - $this->startedAt) * 1000, 2);
    }

    /** @return array<string, float> */
    public function marks(): array
    {
        return $this->marks;
    }

    public function queryCount(): int
    {
        return $this->queryCount;
    }

    public function queryTimeMs(): float
    {
        return round($this->queryTimeMs, 2);
    }

    /** @return list<array{sql: string, time_ms: float}> */
    public function slowQueries(): array
    {
        return $this->slowQueries;
    }

    /** @return list<Throwable> */
    public function exceptions(): array
    {
        return array_values($this->exceptions);
    }

    /**
     * Registered once per event dispatcher, and only by a process that has
     * served an API request — queue workers and console commands never pay for
     * them. Tracked per dispatcher (not a plain flag) because the test suite
     * boots a fresh application, and dispatcher, for every test.
     */
    private static function registerListeners(): void
    {
        $dispatcher = Event::getFacadeRoot();

        if (self::$listeningOn?->get() === $dispatcher) {
            return;
        }

        self::$listeningOn = WeakReference::create($dispatcher);

        DB::listen(fn (QueryExecuted $query) => app(self::class)->recordQuery($query));
        Event::listen(RouteMatched::class, fn () => app(self::class)->mark('route_matched'));
    }
}
