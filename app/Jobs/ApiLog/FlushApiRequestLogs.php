<?php

namespace App\Jobs\ApiLog;

use App\Support\ApiLogs\ApiLogBuffer;
use App\Support\ApiLogs\ApiLogWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Drains the API log buffer (Redis) into the database in bulk inserts.
 * Scheduled every minute; each run takes up to flush.max_batches batches of
 * flush.batch_size records, and re-dispatches itself when it stops at that cap
 * with the buffer still full — a traffic spike drains continuously without
 * one run holding a worker indefinitely. Pops are atomic, so overlapping runs
 * can never double-insert. A no-op under the sync buffer, which writes directly.
 *
 * Deliberately not a sub-minute schedule: any sub-minute task keeps every
 * `schedule:run` invocation alive for the whole minute, app-wide.
 */
class FlushApiRequestLogs implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function handle(ApiLogBuffer $buffer, ApiLogWriter $writer): int
    {
        $batchSize = max(1, (int) config('api_logs.flush.batch_size', 1000));
        $maxBatches = max(1, (int) config('api_logs.flush.max_batches', 50));
        $flushed = 0;

        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $records = $buffer->pop($batchSize);

            if ($records === []) {
                return $flushed;
            }

            $writer->write($records);
            $flushed += count($records);

            if (count($records) < $batchSize) {
                return $flushed;
            }
        }

        // Stopped at the batch cap with the last batch full: there's a backlog.
        self::dispatch();

        return $flushed;
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: FlushApiRequestLogs', [
            'job' => self::class,
            'exception' => $exception,
        ]);
    }
}
