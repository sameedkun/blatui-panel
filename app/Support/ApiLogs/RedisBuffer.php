<?php

declare(strict_types=1);

namespace App\Support\ApiLogs;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

/**
 * A Redis list as the hand-off between the request process and the database:
 * a request pays for one RPUSH (sub-millisecond, and after its response has
 * already been sent), and FlushApiRequestLogs drains the list in batches of
 * bulk INSERTs. A crash mid-flush loses at most the one batch it had popped —
 * an accepted trade-off for logs, in exchange for zero DB writes per request.
 */
class RedisBuffer implements ApiLogBuffer
{
    public function push(array $record): void
    {
        $this->connection()->rpush($this->key(), json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function pop(int $count): array
    {
        $key = $this->key();

        // LRANGE + LTRIM inside MULTI/EXEC — atomic, so two concurrent flushes
        // can never pop the same records.
        $results = $this->connection()->transaction(function ($transaction) use ($key, $count): void {
            $transaction->lrange($key, 0, $count - 1);
            $transaction->ltrim($key, $count, -1);
        });

        $items = is_array($results) ? ($results[0] ?? []) : [];

        return array_values(array_filter(array_map(
            fn (mixed $item): mixed => is_string($item) ? json_decode($item, true) : null,
            is_array($items) ? $items : [],
        ), 'is_array'));
    }

    private function connection(): Connection
    {
        return Redis::connection(config('api_logs.redis.connection', 'default'));
    }

    private function key(): string
    {
        return (string) config('api_logs.redis.key', 'api_logs:buffer');
    }
}
