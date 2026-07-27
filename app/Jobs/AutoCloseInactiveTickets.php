<?php

namespace App\Jobs;

use App\Services\TicketLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin scheduled trigger for the ticket auto-close sweep. All logic lives in
 * {@see TicketLifecycleService::autoCloseInactive()} — this job just invokes it.
 */
class AutoCloseInactiveTickets implements ShouldQueue
{
    use Queueable;

    /**
     * The sweep is idempotent per ticket (a closed ticket no longer matches
     * the query), so a failed run is not retried — the next scheduled run
     * catches any stragglers.
     */
    public int $tries = 1;

    public int $timeout = 300;

    public function handle(TicketLifecycleService $lifecycle): void
    {
        $lifecycle->autoCloseInactive(config('panel.ticket_auto_close_inactive_days'));
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: AutoCloseInactiveTickets', [
            'job' => self::class,
            'exception' => $exception,
        ]);
    }
}
