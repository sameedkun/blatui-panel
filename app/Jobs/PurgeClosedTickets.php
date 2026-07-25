<?php

namespace App\Jobs;

use App\Services\TicketLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Thin scheduled trigger for the closed-ticket retention purge. All logic
 * lives in {@see TicketLifecycleService::purgeClosedTickets()} — this job
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

    public function __construct(public readonly int $months) {}

    public function handle(TicketLifecycleService $lifecycle): void
    {
        $lifecycle->purgeClosedTickets($this->months);
    }
}
