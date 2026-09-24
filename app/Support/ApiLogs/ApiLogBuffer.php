<?php

declare(strict_types=1);

namespace App\Support\ApiLogs;

/**
 * Where a finished request's record goes from terminate(). Bound in
 * AppServiceProvider from config('api_logs.buffer'): {@see RedisBuffer} in
 * production (one RPUSH per request, drained in bulk by
 * FlushApiRequestLogs), {@see SyncBuffer} for tests and local debugging.
 *
 * @phpstan-import-type ApiLogRecord from RecordBuilder
 */
interface ApiLogBuffer
{
    /**
     * @param  ApiLogRecord  $record
     */
    public function push(array $record): void;

    /**
     * Atomically remove and return up to $count buffered records, oldest first.
     *
     * @return list<ApiLogRecord>
     */
    public function pop(int $count): array;
}
