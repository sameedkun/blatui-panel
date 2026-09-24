<?php

declare(strict_types=1);

namespace App\Support\ApiLogs;

/**
 * Writes each record straight to the database — no background flush needed.
 * Used by the test suite (phpunit.xml sets API_LOG_BUFFER=sync) and handy
 * locally without a scheduler running. Still runs from terminate(), after the
 * response has been sent.
 */
class SyncBuffer implements ApiLogBuffer
{
    public function __construct(private readonly ApiLogWriter $writer) {}

    public function push(array $record): void
    {
        $this->writer->write([$record]);
    }

    public function pop(int $count): array
    {
        return [];
    }
}
