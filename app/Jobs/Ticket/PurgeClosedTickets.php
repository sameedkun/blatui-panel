<?php

namespace App\Jobs\Ticket;

use App\Services\Ticket\LifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin scheduled trigger for the closed-ticket retention purge. All logic
 * lives in {@see LifecycleService::purgeClosedTickets()} — this job
 * just invokes it.
 */
class PurgeClosedTickets implements ShouldQueue
{
    use Queueable;

    /**
     * Purge is destructive and idempotent per ticket, so a failed sweep is
     * not retried — the next scheduled run catches any stragglers.
     */
    public int $tries = 1;

    public int $timeout = 300;

    public function handle(LifecycleService $lifecycle): void
    {
        $lifecycle->purgeClosedTickets(config('panel.ticket_purge_closed_after_months'));
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: PurgeClosedTickets', [
            'job' => self::class,
            'exception' => $exception,
        ]);
    }
}
